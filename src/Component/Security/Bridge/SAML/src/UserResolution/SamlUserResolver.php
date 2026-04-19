<?php

/*
 * This file is part of the WPPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WPPack\Component\Security\Bridge\SAML\UserResolution;

use Psr\EventDispatcher\EventDispatcherInterface;
use WPPack\Component\Sanitizer\Sanitizer;
use WPPack\Component\Security\Bridge\SAML\Event\SamlUserAttributesMappedEvent;
use WPPack\Component\Security\Bridge\SAML\Event\SamlUserProvisionedEvent;
use WPPack\Component\Security\Bridge\SAML\Event\SamlUserProvisionFailedEvent;
use WPPack\Component\Security\Bridge\SAML\Event\SamlUserUpdatedEvent;
use WPPack\Component\Security\Exception\AuthenticationException;
use WPPack\Component\User\UserRepositoryInterface;

final class SamlUserResolver implements SamlUserResolverInterface
{
    /**
     * @param list<SamlAttributeMapping> $customMappings Custom SAML attribute → user meta mappings
     */
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly Sanitizer $sanitizer,
        private readonly bool $autoProvision = false,
        private readonly string $emailAttribute = 'email',
        private readonly ?string $firstNameAttribute = 'firstName',
        private readonly ?string $lastNameAttribute = 'lastName',
        private readonly ?string $displayNameAttribute = 'displayName',
        private readonly array $customMappings = [],
        private readonly ?EventDispatcherInterface $dispatcher = null,
    ) {}

    private const SAML_NAMEID_META_KEY = '_wppack_saml_nameid';

    /**
     * @param array<string, list<string>> $attributes
     */
    public function resolveUser(string $nameId, array $attributes): \WP_User
    {
        $sanitizedNameId = $this->sanitizer->user($nameId, true);

        if ($sanitizedNameId === '') {
            throw new AuthenticationException('Invalid SAML NameID.');
        }

        $email = $this->getAttributeValue($attributes, $this->emailAttribute);

        if ($email !== null) {
            $email = $this->sanitizer->email($email);

            if ($email === '' || !filter_var($email, \FILTER_VALIDATE_EMAIL)) {
                $email = null;
            }
        }

        $user = $this->findByNameId($sanitizedNameId);

        if ($user instanceof \WP_User) {
            $this->syncUserAttributes($user, $sanitizedNameId, $attributes, $email);

            return $user;
        }

        if ($email !== null) {
            $user = $this->userRepository->findByEmail($email);

            if ($user instanceof \WP_User) {
                if (!$this->isNameIdBound($user, $sanitizedNameId)) {
                    throw new AuthenticationException('SAML NameID mismatch for existing user.');
                }

                $this->syncUserAttributes($user, $sanitizedNameId, $attributes, $email);

                return $user;
            }
        }

        $user = $this->userRepository->findByLogin($sanitizedNameId);

        if ($user instanceof \WP_User) {
            $this->bindNameId($user, $sanitizedNameId);
            $this->syncUserAttributes($user, $sanitizedNameId, $attributes, $email);

            return $user;
        }

        if (!$this->autoProvision) {
            throw new AuthenticationException(\sprintf(
                'User "%s" not found and auto-provisioning is disabled.',
                $sanitizedNameId,
            ));
        }

        return $this->provisionUser($sanitizedNameId, $email, $attributes);
    }

    /**
     * @param array<string, list<string>> $attributes
     */
    private function provisionUser(string $nameId, ?string $email, array $attributes): \WP_User
    {
        $userdata = [
            'user_login' => $nameId,
            'user_email' => $email ?? $nameId,
            'user_pass' => wp_generate_password(32, true, true),
        ];

        if ($this->firstNameAttribute !== null) {
            $firstName = $this->getAttributeValue($attributes, $this->firstNameAttribute);

            if ($firstName !== null) {
                $userdata['first_name'] = $this->sanitizer->text($firstName);
            }
        }

        if ($this->lastNameAttribute !== null) {
            $lastName = $this->getAttributeValue($attributes, $this->lastNameAttribute);

            if ($lastName !== null) {
                $userdata['last_name'] = $this->sanitizer->text($lastName);
            }
        }

        if ($this->displayNameAttribute !== null) {
            $displayName = $this->getAttributeValue($attributes, $this->displayNameAttribute);

            if ($displayName !== null) {
                $userdata['display_name'] = $this->sanitizer->text($displayName);
            }
        }

        // Apply custom mappings to user meta
        $userMeta = [];

        foreach ($this->customMappings as $mapping) {
            $value = $this->getAttributeValue($attributes, $mapping->samlAttribute);

            if ($value !== null) {
                $userMeta[$mapping->metaKey] = $this->sanitizer->text($value);
            }
        }

        if ($this->dispatcher !== null) {
            $event = $this->dispatcher->dispatch(
                new SamlUserAttributesMappedEvent($userdata, $userMeta, $attributes, $nameId, isNewUser: true),
            );
            $userdata = $event->getUserdata();
            $userMeta = $event->getUserMeta();
        }

        // Store SSO attributes + custom mappings via meta_input (available before user_register fires)
        $userdata['meta_input'] = array_merge($userMeta, [
            self::SAML_NAMEID_META_KEY => $nameId,
            '_wppack_sso_source' => 'saml',
            '_wppack_saml_attributes' => json_encode($attributes, \JSON_UNESCAPED_UNICODE),
        ]);

        try {
            $userId = $this->userRepository->insert($userdata);
        } catch (\Throwable $e) {
            $this->dispatcher?->dispatch(new SamlUserProvisionFailedEvent($nameId, new \WP_Error('provision_failed', $e->getMessage())));

            throw new AuthenticationException('User provisioning failed.');
        }

        $user = $this->userRepository->find($userId);

        // @codeCoverageIgnoreStart
        if (!$user instanceof \WP_User) {
            throw new AuthenticationException(\sprintf('Failed to retrieve provisioned user "%s".', $nameId));
        }
        // @codeCoverageIgnoreEnd

        // NameID binding and custom mappings already stored via meta_input

        $this->dispatcher?->dispatch(new SamlUserProvisionedEvent($user, $nameId, $attributes));

        return $user;
    }

    /**
     * @param array<string, list<string>> $attributes
     */
    private function syncUserAttributes(\WP_User $user, string $nameId, array $attributes, ?string $email = null): void
    {
        $userdata = ['ID' => $user->ID];
        $needsUpdate = false;

        if ($email !== null && $email !== $user->user_email) {
            $userdata['user_email'] = $email;
            $needsUpdate = true;
        }

        if ($this->firstNameAttribute !== null) {
            $firstName = $this->getAttributeValue($attributes, $this->firstNameAttribute);

            if ($firstName !== null) {
                $firstName = $this->sanitizer->text($firstName);

                if ($firstName !== $user->first_name) {
                    $userdata['first_name'] = $firstName;
                    $needsUpdate = true;
                }
            }
        }

        if ($this->lastNameAttribute !== null) {
            $lastName = $this->getAttributeValue($attributes, $this->lastNameAttribute);

            if ($lastName !== null) {
                $lastName = $this->sanitizer->text($lastName);

                if ($lastName !== $user->last_name) {
                    $userdata['last_name'] = $lastName;
                    $needsUpdate = true;
                }
            }
        }

        if ($this->displayNameAttribute !== null) {
            $displayName = $this->getAttributeValue($attributes, $this->displayNameAttribute);

            if ($displayName !== null) {
                $displayName = $this->sanitizer->text($displayName);

                if ($displayName !== $user->display_name) {
                    $userdata['display_name'] = $displayName;
                    $needsUpdate = true;
                }
            }
        }

        // Apply custom mappings to user meta
        $userMeta = [];

        foreach ($this->customMappings as $mapping) {
            $value = $this->getAttributeValue($attributes, $mapping->samlAttribute);

            if ($value !== null) {
                $userMeta[$mapping->metaKey] = $this->sanitizer->text($value);
            }
        }

        if ($this->dispatcher !== null) {
            $event = $this->dispatcher->dispatch(
                new SamlUserAttributesMappedEvent($userdata, $userMeta, $attributes, $nameId, isNewUser: false),
            );
            $userdata = $event->getUserdata();
            $userMeta = $event->getUserMeta();

            // Re-evaluate needsUpdate based on whether userdata has fields beyond 'ID'
            $needsUpdate = \count($userdata) > 1;
        }

        if ($needsUpdate) {
            $this->userRepository->update($userdata);
        }

        foreach ($userMeta as $key => $value) {
            $this->userRepository->updateMeta($user->ID, $key, $value);
        }

        // Always update SSO attributes on every login
        $this->userRepository->updateMeta($user->ID, '_wppack_saml_attributes', json_encode($attributes, \JSON_UNESCAPED_UNICODE));

        if ($needsUpdate || $userMeta !== []) {
            $this->dispatcher?->dispatch(new SamlUserUpdatedEvent($user, $attributes));
        }
    }

    private function isNameIdBound(\WP_User $user, string $nameId): bool
    {
        $storedNameId = $this->userRepository->getMeta($user->ID, self::SAML_NAMEID_META_KEY, true);

        if ($storedNameId === '' || $storedNameId === false) {
            $this->bindNameId($user, $nameId);

            return true;
        }

        return $storedNameId === $nameId;
    }

    private function findByNameId(string $nameId): ?\WP_User
    {
        $users = $this->userRepository->findAll([
            'meta_key' => self::SAML_NAMEID_META_KEY,
            'meta_value' => $nameId,
            'number' => 1,
        ]);

        return $users[0] ?? null;
    }

    private function bindNameId(\WP_User $user, string $nameId): void
    {
        $this->userRepository->updateMeta($user->ID, self::SAML_NAMEID_META_KEY, $nameId);
    }

    /**
     * @param array<string, list<string>> $attributes
     */
    private function getAttributeValue(array $attributes, string $name): ?string
    {
        return $attributes[$name][0] ?? null;
    }
}
