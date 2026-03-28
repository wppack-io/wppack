<?php

/*
 * This file is part of the WpPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WpPack\Component\Security\Bridge\SAML\UserResolution;

use Psr\EventDispatcher\EventDispatcherInterface;
use WpPack\Component\Security\Bridge\SAML\Event\SamlUserAttributesMappedEvent;
use WpPack\Component\Security\Bridge\SAML\Event\SamlUserProvisionedEvent;
use WpPack\Component\Security\Bridge\SAML\Event\SamlUserProvisionFailedEvent;
use WpPack\Component\Security\Bridge\SAML\Event\SamlUserUpdatedEvent;
use WpPack\Component\Security\Exception\AuthenticationException;

final class SamlUserResolver implements SamlUserResolverInterface
{
    /**
     * @param array<string, string>|null  $roleMapping    SAML group value => WordPress role name
     * @param list<SamlAttributeMapping>  $customMappings Custom SAML attribute → user meta mappings
     */
    public function __construct(
        private readonly bool $autoProvision = false,
        private readonly string $defaultRole = 'subscriber',
        private readonly string $emailAttribute = 'email',
        private readonly ?string $firstNameAttribute = 'firstName',
        private readonly ?string $lastNameAttribute = 'lastName',
        private readonly ?string $displayNameAttribute = 'displayName',
        private readonly ?array $roleMapping = null,
        private readonly ?string $roleAttribute = null,
        private readonly array $customMappings = [],
        private readonly ?EventDispatcherInterface $dispatcher = null,
    ) {}

    private const SAML_NAMEID_META_KEY = '_wppack_saml_nameid';

    /**
     * @param array<string, list<string>> $attributes
     */
    public function resolveUser(string $nameId, array $attributes): \WP_User
    {
        $sanitizedNameId = sanitize_user($nameId, true);

        if ($sanitizedNameId === '') {
            throw new AuthenticationException('Invalid SAML NameID.');
        }

        $email = $this->getAttributeValue($attributes, $this->emailAttribute);

        if ($email !== null) {
            $email = sanitize_email($email);

            if ($email === '' || !filter_var($email, \FILTER_VALIDATE_EMAIL)) {
                $email = null;
            }
        }

        $user = $this->findByNameId($sanitizedNameId);

        if ($user instanceof \WP_User) {
            $this->syncUserAttributes($user, $sanitizedNameId, $attributes, $email);
            $this->mapUserRole($user, $attributes);

            return $user;
        }

        if ($email !== null) {
            $user = get_user_by('email', $email);

            if ($user instanceof \WP_User) {
                if (!$this->isNameIdBound($user, $sanitizedNameId)) {
                    throw new AuthenticationException('SAML NameID mismatch for existing user.');
                }

                $this->syncUserAttributes($user, $sanitizedNameId, $attributes, $email);
                $this->mapUserRole($user, $attributes);

                return $user;
            }
        }

        $user = get_user_by('login', $sanitizedNameId);

        if ($user instanceof \WP_User) {
            $this->bindNameId($user, $sanitizedNameId);
            $this->syncUserAttributes($user, $sanitizedNameId, $attributes, $email);
            $this->mapUserRole($user, $attributes);

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
            'role' => $this->defaultRole,
        ];

        if ($this->firstNameAttribute !== null) {
            $firstName = $this->getAttributeValue($attributes, $this->firstNameAttribute);

            if ($firstName !== null) {
                $userdata['first_name'] = sanitize_text_field($firstName);
            }
        }

        if ($this->lastNameAttribute !== null) {
            $lastName = $this->getAttributeValue($attributes, $this->lastNameAttribute);

            if ($lastName !== null) {
                $userdata['last_name'] = sanitize_text_field($lastName);
            }
        }

        if ($this->displayNameAttribute !== null) {
            $displayName = $this->getAttributeValue($attributes, $this->displayNameAttribute);

            if ($displayName !== null) {
                $userdata['display_name'] = sanitize_text_field($displayName);
            }
        }

        // Apply custom mappings to user meta
        $userMeta = [];

        foreach ($this->customMappings as $mapping) {
            $value = $this->getAttributeValue($attributes, $mapping->samlAttribute);

            if ($value !== null) {
                $userMeta[$mapping->metaKey] = sanitize_text_field($value);
            }
        }

        if ($this->dispatcher !== null) {
            $event = $this->dispatcher->dispatch(
                new SamlUserAttributesMappedEvent($userdata, $userMeta, $attributes, $nameId, isNewUser: true),
            );
            $userdata = $event->getUserdata();
            $userMeta = $event->getUserMeta();
        }

        /** @var int|\WP_Error $userId */
        $userId = wp_insert_user($userdata);

        if ($userId instanceof \WP_Error) {
            $this->dispatcher?->dispatch(new SamlUserProvisionFailedEvent($nameId, $userId));

            throw new AuthenticationException('User provisioning failed.');
        }

        $user = get_user_by('id', $userId);

        // @codeCoverageIgnoreStart
        if (!$user instanceof \WP_User) {
            throw new AuthenticationException(\sprintf('Failed to retrieve provisioned user "%s".', $nameId));
        }
        // @codeCoverageIgnoreEnd

        foreach ($userMeta as $key => $value) {
            update_user_meta($userId, $key, $value);
        }

        $this->bindNameId($user, $nameId);
        $this->mapUserRole($user, $attributes);

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
                $firstName = sanitize_text_field($firstName);

                if ($firstName !== $user->first_name) {
                    $userdata['first_name'] = $firstName;
                    $needsUpdate = true;
                }
            }
        }

        if ($this->lastNameAttribute !== null) {
            $lastName = $this->getAttributeValue($attributes, $this->lastNameAttribute);

            if ($lastName !== null) {
                $lastName = sanitize_text_field($lastName);

                if ($lastName !== $user->last_name) {
                    $userdata['last_name'] = $lastName;
                    $needsUpdate = true;
                }
            }
        }

        if ($this->displayNameAttribute !== null) {
            $displayName = $this->getAttributeValue($attributes, $this->displayNameAttribute);

            if ($displayName !== null) {
                $displayName = sanitize_text_field($displayName);

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
                $userMeta[$mapping->metaKey] = sanitize_text_field($value);
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
            wp_update_user($userdata);
        }

        foreach ($userMeta as $key => $value) {
            update_user_meta($user->ID, $key, $value);
        }

        if ($needsUpdate || $userMeta !== []) {
            $this->dispatcher?->dispatch(new SamlUserUpdatedEvent($user, $attributes));
        }
    }

    /**
     * @param array<string, list<string>> $attributes
     */
    private function mapUserRole(\WP_User $user, array $attributes): void
    {
        if ($this->roleMapping === null || $this->roleAttribute === null) {
            return;
        }

        $roleValues = $attributes[$this->roleAttribute] ?? [];

        foreach ($roleValues as $samlRole) {
            if (isset($this->roleMapping[$samlRole])) {
                $user->set_role($this->roleMapping[$samlRole]);

                return;
            }
        }
    }

    private function isNameIdBound(\WP_User $user, string $nameId): bool
    {
        $storedNameId = get_user_meta($user->ID, self::SAML_NAMEID_META_KEY, true);

        if ($storedNameId === '' || $storedNameId === false) {
            $this->bindNameId($user, $nameId);

            return true;
        }

        return $storedNameId === $nameId;
    }

    private function findByNameId(string $nameId): ?\WP_User
    {
        $users = get_users([
            'meta_key' => self::SAML_NAMEID_META_KEY,
            'meta_value' => $nameId,
            'number' => 1,
        ]);

        return $users[0] ?? null;
    }

    private function bindNameId(\WP_User $user, string $nameId): void
    {
        update_user_meta($user->ID, self::SAML_NAMEID_META_KEY, $nameId);
    }

    /**
     * @param array<string, list<string>> $attributes
     */
    private function getAttributeValue(array $attributes, string $name): ?string
    {
        return $attributes[$name][0] ?? null;
    }
}
