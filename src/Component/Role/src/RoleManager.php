<?php

declare(strict_types=1);

namespace WpPack\Component\Role;

use WpPack\Component\Role\Attribute\AsRole;

final class RoleManager
{
    private const MANAGED_STATE_OPTION = 'wppack_managed_roles';

    private const BUILTIN_ROLES = [
        'administrator', 'editor', 'author', 'contributor', 'subscriber',
    ];

    /** @var array<string, RoleDefinition> */
    private array $definitions = [];

    public function addDefinition(RoleDefinition $definition): void
    {
        $this->definitions[$definition->name] = $definition;
    }

    /**
     * @param object|class-string $roleClass
     */
    public function add(object|string $roleClass): void
    {
        $reflection = new \ReflectionClass($roleClass);
        $attributes = $reflection->getAttributes(AsRole::class);

        if ($attributes === []) {
            throw new \LogicException(sprintf(
                'Class "%s" must have the #[AsRole] attribute.',
                is_object($roleClass) ? $roleClass::class : $roleClass,
            ));
        }

        $attr = $attributes[0]->newInstance();

        $this->addDefinition(new RoleDefinition(
            $attr->name,
            $attr->label,
            $attr->capabilities,
        ));
    }

    /**
     * Synchronize role definitions with WordPress database.
     *
     * Uses managed state tracking to safely apply differences:
     * - Adds new roles and capabilities
     * - Removes only capabilities that were previously managed by us
     * - Removes only roles that were previously managed by us
     * - Never touches capabilities or roles added by other plugins
     * - Never removes WordPress built-in roles
     */
    public function synchronize(): void
    {
        $previousState = $this->loadManagedState();

        // Phase 1: Apply current definitions (add/update)
        foreach ($this->definitions as $definition) {
            $wpRole = get_role($definition->name);

            if ($wpRole === null) {
                $capabilities = array_fill_keys($definition->capabilities, true);
                add_role($definition->name, $definition->label, $capabilities);
            } else {
                // Update label if it differs
                $wpRoles = wp_roles();
                if ($wpRoles->roles[$definition->name]['name'] !== $definition->label) {
                    $wpRoles->roles[$definition->name]['name'] = $definition->label;
                    update_option($wpRoles->role_key, $wpRoles->roles);
                }

                // Add missing capabilities
                foreach ($definition->capabilities as $cap) {
                    if (!isset($wpRole->capabilities[$cap]) || !$wpRole->capabilities[$cap]) {
                        $wpRole->add_cap($cap);
                    }
                }
            }

            // Phase 2: Remove orphan capabilities (managed by us before, not in current definition)
            $previousCaps = $previousState[$definition->name] ?? [];
            $orphanCaps = array_diff($previousCaps, $definition->capabilities);

            if ($orphanCaps !== []) {
                $wpRole = get_role($definition->name);
                if ($wpRole !== null) {
                    foreach ($orphanCaps as $cap) {
                        $wpRole->remove_cap($cap);
                    }
                }
            }
        }

        // Phase 3: Remove orphan roles (managed by us before, not in current definitions)
        foreach (array_keys($previousState) as $roleName) {
            if (!isset($this->definitions[$roleName]) && !\in_array($roleName, self::BUILTIN_ROLES, true)) {
                remove_role($roleName);
            }
        }

        // Phase 4: Save current state
        $this->saveManagedState();
    }

    public function unregister(string $roleName): void
    {
        remove_role($roleName);
        unset($this->definitions[$roleName]);
        $this->removeManagedRole($roleName);
    }

    /**
     * @return array<string, RoleDefinition>
     */
    public function all(): array
    {
        return $this->definitions;
    }

    /**
     * @return array<string, list<string>>
     */
    private function loadManagedState(): array
    {
        return get_option(self::MANAGED_STATE_OPTION, []);
    }

    private function saveManagedState(): void
    {
        $state = [];
        foreach ($this->definitions as $definition) {
            $state[$definition->name] = $definition->capabilities;
        }
        update_option(self::MANAGED_STATE_OPTION, $state, false);
    }

    private function removeManagedRole(string $roleName): void
    {
        $state = $this->loadManagedState();
        unset($state[$roleName]);
        update_option(self::MANAGED_STATE_OPTION, $state, false);
    }
}
