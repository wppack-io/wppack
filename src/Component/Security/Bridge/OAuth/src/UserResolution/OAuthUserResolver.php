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

namespace WpPack\Component\Security\Bridge\OAuth\UserResolution;

use Psr\EventDispatcher\EventDispatcherInterface;
use WpPack\Component\Sanitizer\Sanitizer;
use WpPack\Component\Security\Bridge\OAuth\Event\OAuthUserProvisionedEvent;
use WpPack\Component\Security\Bridge\OAuth\Event\OAuthUserProvisionFailedEvent;
use WpPack\Component\Security\Bridge\OAuth\Event\OAuthUserUpdatedEvent;
use WpPack\Component\Security\Exception\AuthenticationException;
use WpPack\Component\User\Exception\UserException;
use WpPack\Component\User\UserRepositoryInterface;

final class OAuthUserResolver implements OAuthUserResolverInterface
{
    private const SUBJECT_META_KEY_PREFIX = '_wppack_oauth_sub_';

    /**
     * @param string $providerName Provider name used in meta key (e.g., 'google', 'azure', 'github')
     */
    public function __construct(
        private readonly string $providerName,
        private readonly UserRepositoryInterface $userRepository,
        private readonly Sanitizer $sanitizer,
        private readonly bool $autoProvision = false,
        private readonly string $emailClaim = 'email',
        private readonly ?string $firstNameClaim = 'given_name',
        private readonly ?string $lastNameClaim = 'family_name',
        private readonly ?string $displayNameClaim = 'name',
        private readonly ?EventDispatcherInterface $dispatcher = null,
    ) {}

    public function resolveUser(string $subject, array $claims): \WP_User
    {
        $sanitizedSubject = $this->sanitizer->user($subject, true);

        if ($sanitizedSubject === '') {
            throw new AuthenticationException('Invalid OAuth subject identifier.');
        }

        // 1. Try to find user by bound subject ID (meta query)
        $user = $this->findBySubject($sanitizedSubject);

        if ($user !== null) {
            $this->syncUserAttributes($user, $claims);

            return $user;
        }

        // 2. Try to find by email
        $email = $this->getClaimValue($claims, $this->emailClaim);

        if ($email !== null) {
            $email = $this->sanitizer->email($email);

            if ($email === '' || !filter_var($email, \FILTER_VALIDATE_EMAIL)) {
                $email = null;
            }
        }

        if ($email !== null) {
            $user = $this->userRepository->findByEmail($email);

            if ($user instanceof \WP_User) {
                if (!$this->isSubjectBound($user, $sanitizedSubject)) {
                    throw new AuthenticationException('OAuth subject mismatch for existing user.');
                }

                $this->bindSubject($user, $sanitizedSubject);
                $this->syncUserAttributes($user, $claims);

                return $user;
            }
        }

        // 3. Try by login
        $user = $this->userRepository->findByLogin($sanitizedSubject);

        if ($user instanceof \WP_User) {
            $this->bindSubject($user, $sanitizedSubject);
            $this->syncUserAttributes($user, $claims);

            return $user;
        }

        // 4. Provision if registration is enabled
        if (!$this->autoProvision) {
            throw new AuthenticationException(\sprintf(
                'User "%s" not found and auto-provisioning is disabled.',
                $sanitizedSubject,
            ));
        }

        return $this->provisionUser($sanitizedSubject, $email, $claims);
    }

    private function getSubjectMetaKey(): string
    {
        return self::SUBJECT_META_KEY_PREFIX . $this->providerName;
    }

    private function findBySubject(string $subject): ?\WP_User
    {
        $users = $this->userRepository->findAll([
            'meta_key' => $this->getSubjectMetaKey(),
            'meta_value' => $subject,
            'number' => 1,
        ]);

        return $users[0] ?? null;
    }

    private function isSubjectBound(\WP_User $user, string $subject): bool
    {
        $storedSubject = $this->userRepository->getMeta($user->ID, $this->getSubjectMetaKey(), true);

        if ($storedSubject === '' || $storedSubject === false) {
            return true; // Not yet bound, OK to bind
        }

        return $storedSubject === $subject;
    }

    private function bindSubject(\WP_User $user, string $subject): void
    {
        $this->userRepository->updateMeta($user->ID, $this->getSubjectMetaKey(), $subject);
    }

    /**
     * @param array<string, mixed> $claims
     */
    private function provisionUser(string $subject, ?string $email, array $claims): \WP_User
    {
        $userdata = [
            'user_login' => $subject,
            'user_email' => $email ?? $subject,
            'user_pass' => wp_generate_password(32, true, true),
        ];

        if ($this->firstNameClaim !== null) {
            $firstName = $this->getClaimValue($claims, $this->firstNameClaim);

            if ($firstName !== null) {
                $userdata['first_name'] = $this->sanitizer->text($firstName);
            }
        }

        if ($this->lastNameClaim !== null) {
            $lastName = $this->getClaimValue($claims, $this->lastNameClaim);

            if ($lastName !== null) {
                $userdata['last_name'] = $this->sanitizer->text($lastName);
            }
        }

        if ($this->displayNameClaim !== null) {
            $displayName = $this->getClaimValue($claims, $this->displayNameClaim);

            if ($displayName !== null) {
                $userdata['display_name'] = $this->sanitizer->text($displayName);
            }
        }

        try {
            $userId = $this->userRepository->insert($userdata);
        } catch (UserException $e) {
            $this->dispatcher?->dispatch(new OAuthUserProvisionFailedEvent(
                $subject,
                new \WP_Error('provision_failed', $e->getMessage()),
            ));

            throw new AuthenticationException('User provisioning failed.');
        }

        $user = $this->userRepository->find($userId);

        if (!$user instanceof \WP_User) {
            throw new AuthenticationException(\sprintf('Failed to retrieve provisioned user "%s".', $subject));
        }

        $this->bindSubject($user, $subject);

        $this->dispatcher?->dispatch(new OAuthUserProvisionedEvent($user, $subject, $claims));

        return $user;
    }

    /**
     * @param array<string, mixed> $claims
     */
    private function syncUserAttributes(\WP_User $user, array $claims): void
    {
        $userdata = ['ID' => $user->ID];
        $needsUpdate = false;

        if ($this->firstNameClaim !== null) {
            $firstName = $this->getClaimValue($claims, $this->firstNameClaim);

            if ($firstName !== null) {
                $firstName = $this->sanitizer->text($firstName);

                if ($firstName !== $user->first_name) {
                    $userdata['first_name'] = $firstName;
                    $needsUpdate = true;
                }
            }
        }

        if ($this->lastNameClaim !== null) {
            $lastName = $this->getClaimValue($claims, $this->lastNameClaim);

            if ($lastName !== null) {
                $lastName = $this->sanitizer->text($lastName);

                if ($lastName !== $user->last_name) {
                    $userdata['last_name'] = $lastName;
                    $needsUpdate = true;
                }
            }
        }

        if ($this->displayNameClaim !== null) {
            $displayName = $this->getClaimValue($claims, $this->displayNameClaim);

            if ($displayName !== null) {
                $displayName = $this->sanitizer->text($displayName);

                if ($displayName !== $user->display_name) {
                    $userdata['display_name'] = $displayName;
                    $needsUpdate = true;
                }
            }
        }

        if ($needsUpdate) {
            $this->userRepository->update($userdata);
            $this->dispatcher?->dispatch(new OAuthUserUpdatedEvent($user, $claims));
        }
    }

    /**
     * @param array<string, mixed> $claims
     */
    private function getClaimValue(array $claims, string $name): ?string
    {
        $value = $claims[$name] ?? null;

        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return null;
        }

        return is_string($value) || is_int($value) ? (string) $value : null;
    }
}
