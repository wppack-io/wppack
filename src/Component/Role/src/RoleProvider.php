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

namespace WPPack\Component\Role;

readonly class RoleProvider
{
    /**
     * @return array{name: string, capabilities: array<string, bool>}|null
     */
    public function find(string $roleName): ?array
    {
        $role = wp_roles()->roles[$roleName] ?? null;

        return $role !== null ? $this->normalizeRole($role) : null;
    }

    /**
     * @return array<string, array{name: string, capabilities: array<string, bool>}>
     */
    public function all(): array
    {
        $roles = [];
        foreach (wp_roles()->roles as $name => $role) {
            $normalized = $this->normalizeRole($role);
            if ($normalized !== null) {
                $roles[$name] = $normalized;
            }
        }

        return $roles;
    }

    /**
     * WP stubs type `wp_roles()->roles` as `array<string, array>` (no
     * shape). Validate/narrow to the project contract before returning.
     *
     * @param array<string, mixed> $role
     *
     * @return array{name: string, capabilities: array<string, bool>}|null
     */
    private function normalizeRole(array $role): ?array
    {
        $name = $role['name'] ?? null;
        $capabilities = $role['capabilities'] ?? null;

        if (!\is_string($name) || !\is_array($capabilities)) {
            return null;
        }

        $narrowedCaps = [];
        foreach ($capabilities as $cap => $has) {
            if (\is_string($cap) && \is_bool($has)) {
                $narrowedCaps[$cap] = $has;
            }
        }

        return ['name' => $name, 'capabilities' => $narrowedCaps];
    }

    /**
     * @return array<string, string> roleName => displayName
     */
    public function getNames(): array
    {
        return array_map('translate_user_role', wp_roles()->get_names());
    }

    /**
     * @param array<string, bool> $capabilities
     */
    public function add(string $name, string $label, array $capabilities = []): void
    {
        add_role($name, $label, $capabilities);
    }

    public function remove(string $name): void
    {
        remove_role($name);
    }

    public function updateLabel(string $name, string $label): void
    {
        $wpRoles = wp_roles();

        if (isset($wpRoles->roles[$name])) {
            $wpRoles->roles[$name]['name'] = $label;
            update_option($wpRoles->role_key, $wpRoles->roles);
        }
    }

    public function addCapability(string $roleName, string $capability): void
    {
        $wpRole = get_role($roleName);

        if ($wpRole !== null) {
            $wpRole->add_cap($capability);
        }
    }

    public function removeCapability(string $roleName, string $capability): void
    {
        $wpRole = get_role($roleName);

        if ($wpRole !== null) {
            $wpRole->remove_cap($capability);
        }
    }
}
