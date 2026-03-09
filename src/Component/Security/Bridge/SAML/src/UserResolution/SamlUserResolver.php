<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Bridge\SAML\UserResolution;

use WpPack\Component\Security\Exception\AuthenticationException;

final class SamlUserResolver implements SamlUserResolverInterface
{
    /**
     * @param array<string, string>|null $roleMapping SAML group value => WordPress role name
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
    ) {}

    /**
     * @param array<string, list<string>> $attributes
     */
    public function resolveUser(string $nameId, array $attributes): \WP_User
    {
        if (!function_exists('get_user_by')) {
            throw new \RuntimeException('WordPress functions are not available.');
        }

        $email = $this->getAttributeValue($attributes, $this->emailAttribute);

        if ($email !== null) {
            $user = get_user_by('email', $email);

            if ($user instanceof \WP_User) {
                $this->syncUserAttributes($user, $attributes);
                $this->mapUserRole($user, $attributes);

                return $user;
            }
        }

        $user = get_user_by('login', $nameId);

        if ($user instanceof \WP_User) {
            $this->syncUserAttributes($user, $attributes);
            $this->mapUserRole($user, $attributes);

            return $user;
        }

        if (!$this->autoProvision) {
            throw new AuthenticationException(\sprintf(
                'User "%s" not found and auto-provisioning is disabled.',
                $nameId,
            ));
        }

        return $this->provisionUser($nameId, $email, $attributes);
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
                $userdata['first_name'] = $firstName;
            }
        }

        if ($this->lastNameAttribute !== null) {
            $lastName = $this->getAttributeValue($attributes, $this->lastNameAttribute);

            if ($lastName !== null) {
                $userdata['last_name'] = $lastName;
            }
        }

        if ($this->displayNameAttribute !== null) {
            $displayName = $this->getAttributeValue($attributes, $this->displayNameAttribute);

            if ($displayName !== null) {
                $userdata['display_name'] = $displayName;
            }
        }

        /** @var int|\WP_Error $userId */
        $userId = wp_insert_user($userdata);

        if ($userId instanceof \WP_Error) {
            throw new AuthenticationException(\sprintf(
                'Failed to provision user "%s": %s',
                $nameId,
                $userId->get_error_message(),
            ));
        }

        $user = get_user_by('id', $userId);

        if (!$user instanceof \WP_User) {
            throw new AuthenticationException(\sprintf('Failed to retrieve provisioned user "%s".', $nameId));
        }

        $this->mapUserRole($user, $attributes);

        return $user;
    }

    /**
     * @param array<string, list<string>> $attributes
     */
    private function syncUserAttributes(\WP_User $user, array $attributes): void
    {
        if (!function_exists('wp_update_user')) {
            return;
        }

        $userdata = ['ID' => $user->ID];
        $needsUpdate = false;

        if ($this->firstNameAttribute !== null) {
            $firstName = $this->getAttributeValue($attributes, $this->firstNameAttribute);

            if ($firstName !== null && $firstName !== $user->first_name) {
                $userdata['first_name'] = $firstName;
                $needsUpdate = true;
            }
        }

        if ($this->lastNameAttribute !== null) {
            $lastName = $this->getAttributeValue($attributes, $this->lastNameAttribute);

            if ($lastName !== null && $lastName !== $user->last_name) {
                $userdata['last_name'] = $lastName;
                $needsUpdate = true;
            }
        }

        if ($this->displayNameAttribute !== null) {
            $displayName = $this->getAttributeValue($attributes, $this->displayNameAttribute);

            if ($displayName !== null && $displayName !== $user->display_name) {
                $userdata['display_name'] = $displayName;
                $needsUpdate = true;
            }
        }

        if ($needsUpdate) {
            wp_update_user($userdata);
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

    /**
     * @param array<string, list<string>> $attributes
     */
    private function getAttributeValue(array $attributes, string $name): ?string
    {
        return $attributes[$name][0] ?? null;
    }
}
