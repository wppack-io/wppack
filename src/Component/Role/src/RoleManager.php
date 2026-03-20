<?php

declare(strict_types=1);

namespace WpPack\Component\Role;

use WpPack\Component\Role\Attribute\AsRole;

final class RoleManager
{
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
     * Compares PHP definitions with wp_user_roles option and applies differences:
     * - Adds new roles
     * - Adds new capabilities to existing roles
     * - Removes capabilities no longer in the definition
     */
    public function synchronize(): void
    {
        foreach ($this->definitions as $definition) {
            $wpRole = get_role($definition->name);

            if ($wpRole === null) {
                $capabilities = array_fill_keys($definition->capabilities, true);
                add_role($definition->name, $definition->label, $capabilities);

                continue;
            }

            // Update label if it differs
            $wpRoles = wp_roles();
            if ($wpRoles->roles[$definition->name]['name'] !== $definition->label) {
                $wpRoles->roles[$definition->name]['name'] = $definition->label;
                update_option($wpRoles->role_key, $wpRoles->roles);
            }

            $definedCaps = array_flip($definition->capabilities);

            // Add missing capabilities
            foreach ($definition->capabilities as $cap) {
                if (!isset($wpRole->capabilities[$cap]) || !$wpRole->capabilities[$cap]) {
                    $wpRole->add_cap($cap);
                }
            }

            // Remove capabilities no longer in the definition
            foreach (array_keys($wpRole->capabilities) as $cap) {
                if (!isset($definedCaps[$cap])) {
                    $wpRole->remove_cap($cap);
                }
            }
        }
    }

    public function remove(string $roleName): void
    {
        remove_role($roleName);
        unset($this->definitions[$roleName]);
    }

    /**
     * @return array<string, RoleDefinition>
     */
    public function all(): array
    {
        return $this->definitions;
    }
}
