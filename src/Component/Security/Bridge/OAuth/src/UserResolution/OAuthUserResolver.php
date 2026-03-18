<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Bridge\OAuth\UserResolution;

use WpPack\Component\Security\Exception\AuthenticationException;

final class OAuthUserResolver implements OAuthUserResolverInterface
{
    private const SUBJECT_META_KEY_PREFIX = '_wppack_oauth_sub_';

    /**
     * @param string $providerName Provider name used in meta key (e.g., 'google', 'azure', 'github')
     * @param bool $autoProvision Enable JIT user provisioning
     * @param string $defaultRole Default role for new users
     * @param string $emailClaim Claim name for email
     * @param string|null $firstNameClaim Claim name for first name
     * @param string|null $lastNameClaim Claim name for last name
     * @param string|null $displayNameClaim Claim name for display name
     * @param array<string, string>|null $roleMapping IdP role value => WordPress role name
     * @param string|null $roleClaim Claim name containing role information
     */
    public function __construct(
        private readonly string $providerName,
        private readonly bool $autoProvision = false,
        private readonly string $defaultRole = 'subscriber',
        private readonly string $emailClaim = 'email',
        private readonly ?string $firstNameClaim = 'given_name',
        private readonly ?string $lastNameClaim = 'family_name',
        private readonly ?string $displayNameClaim = 'name',
        private readonly ?array $roleMapping = null,
        private readonly ?string $roleClaim = null,
    ) {}

    public function resolveUser(string $subject, array $claims): \WP_User
    {
        $sanitizedSubject = sanitize_user($subject, true);

        if ($sanitizedSubject === '') {
            throw new AuthenticationException('Invalid OAuth subject identifier.');
        }

        // 1. Try to find user by bound subject ID (meta query)
        $user = $this->findBySubject($sanitizedSubject);

        if ($user !== null) {
            $this->syncUserAttributes($user, $claims);
            $this->mapUserRole($user, $claims);

            return $user;
        }

        // 2. Try to find by email
        $email = $this->getClaimValue($claims, $this->emailClaim);

        if ($email !== null) {
            $email = sanitize_email($email);

            if ($email === '' || !filter_var($email, \FILTER_VALIDATE_EMAIL)) {
                $email = null;
            }
        }

        if ($email !== null) {
            $user = get_user_by('email', $email);

            if ($user instanceof \WP_User) {
                if (!$this->isSubjectBound($user, $sanitizedSubject)) {
                    throw new AuthenticationException('OAuth subject mismatch for existing user.');
                }

                $this->bindSubject($user, $sanitizedSubject);
                $this->syncUserAttributes($user, $claims);
                $this->mapUserRole($user, $claims);

                return $user;
            }
        }

        // 3. Try by login
        $user = get_user_by('login', $sanitizedSubject);

        if ($user instanceof \WP_User) {
            $this->bindSubject($user, $sanitizedSubject);
            $this->syncUserAttributes($user, $claims);
            $this->mapUserRole($user, $claims);

            return $user;
        }

        // 4. Auto-provision if enabled
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
        $users = get_users([
            'meta_key' => $this->getSubjectMetaKey(),
            'meta_value' => $subject,
            'number' => 1,
        ]);

        return $users[0] ?? null;
    }

    private function isSubjectBound(\WP_User $user, string $subject): bool
    {
        $storedSubject = get_user_meta($user->ID, $this->getSubjectMetaKey(), true);

        if ($storedSubject === '' || $storedSubject === false) {
            return true; // Not yet bound, OK to bind
        }

        return $storedSubject === $subject;
    }

    private function bindSubject(\WP_User $user, string $subject): void
    {
        update_user_meta($user->ID, $this->getSubjectMetaKey(), $subject);
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
            'role' => $this->defaultRole,
        ];

        if ($this->firstNameClaim !== null) {
            $firstName = $this->getClaimValue($claims, $this->firstNameClaim);

            if ($firstName !== null) {
                $userdata['first_name'] = sanitize_text_field($firstName);
            }
        }

        if ($this->lastNameClaim !== null) {
            $lastName = $this->getClaimValue($claims, $this->lastNameClaim);

            if ($lastName !== null) {
                $userdata['last_name'] = sanitize_text_field($lastName);
            }
        }

        if ($this->displayNameClaim !== null) {
            $displayName = $this->getClaimValue($claims, $this->displayNameClaim);

            if ($displayName !== null) {
                $userdata['display_name'] = sanitize_text_field($displayName);
            }
        }

        /** @var int|\WP_Error $userId */
        $userId = wp_insert_user($userdata);

        if ($userId instanceof \WP_Error) {
            do_action('wppack_oauth_user_provision_failed', $subject, $userId);

            throw new AuthenticationException('User provisioning failed.');
        }

        $user = get_user_by('id', $userId);

        if (!$user instanceof \WP_User) {
            throw new AuthenticationException(\sprintf('Failed to retrieve provisioned user "%s".', $subject));
        }

        $this->bindSubject($user, $subject);
        $this->mapUserRole($user, $claims);

        do_action('wppack_oauth_user_provisioned', $user, $subject, $claims);

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
                $firstName = sanitize_text_field($firstName);

                if ($firstName !== $user->first_name) {
                    $userdata['first_name'] = $firstName;
                    $needsUpdate = true;
                }
            }
        }

        if ($this->lastNameClaim !== null) {
            $lastName = $this->getClaimValue($claims, $this->lastNameClaim);

            if ($lastName !== null) {
                $lastName = sanitize_text_field($lastName);

                if ($lastName !== $user->last_name) {
                    $userdata['last_name'] = $lastName;
                    $needsUpdate = true;
                }
            }
        }

        if ($this->displayNameClaim !== null) {
            $displayName = $this->getClaimValue($claims, $this->displayNameClaim);

            if ($displayName !== null) {
                $displayName = sanitize_text_field($displayName);

                if ($displayName !== $user->display_name) {
                    $userdata['display_name'] = $displayName;
                    $needsUpdate = true;
                }
            }
        }

        if ($needsUpdate) {
            wp_update_user($userdata);
            do_action('wppack_oauth_user_updated', $user, $claims);
        }
    }

    /**
     * @param array<string, mixed> $claims
     */
    private function mapUserRole(\WP_User $user, array $claims): void
    {
        if ($this->roleMapping === null || $this->roleClaim === null) {
            return;
        }

        $roleValue = $this->getClaimValue($claims, $this->roleClaim);

        if ($roleValue !== null) {
            if (isset($this->roleMapping[$roleValue])) {
                $user->set_role($this->roleMapping[$roleValue]);
            }

            return;
        }

        // Try array claim
        $roleValues = $claims[$this->roleClaim] ?? [];

        if (is_array($roleValues)) {
            foreach ($roleValues as $role) {
                if (is_string($role) && isset($this->roleMapping[$role])) {
                    $user->set_role($this->roleMapping[$role]);

                    return;
                }
            }
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
