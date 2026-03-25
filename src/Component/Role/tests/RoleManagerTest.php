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

namespace WpPack\Component\Role\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Role\Attribute\AsRole;
use WpPack\Component\Role\RoleDefinition;
use WpPack\Component\Role\RoleManager;

final class RoleManagerTest extends TestCase
{
    private RoleManager $manager;

    protected function setUp(): void
    {
        $this->manager = new RoleManager();
    }

    protected function tearDown(): void
    {
        // Clean up any roles created during tests
        foreach ($this->manager->all() as $definition) {
            remove_role($definition->name);
        }

        delete_option('wppack_managed_roles');
    }

    #[Test]
    public function addDefinitionStoresDefinition(): void
    {
        $definition = new RoleDefinition('test_role', 'Test Role', ['read']);

        $this->manager->addDefinition($definition);

        $definitions = $this->manager->all();
        self::assertCount(1, $definitions);
        self::assertArrayHasKey('test_role', $definitions);
        self::assertSame('Test Role', $definitions['test_role']->label);
        self::assertSame(['read'], $definitions['test_role']->capabilities);
    }

    #[Test]
    public function addResolvesFromAsRoleAttribute(): void
    {
        $this->manager->add(ShopManagerTestRole::class);

        $definitions = $this->manager->all();
        self::assertCount(1, $definitions);
        self::assertArrayHasKey('shop_manager', $definitions);
        self::assertSame('Shop Manager', $definitions['shop_manager']->label);
        self::assertSame(['read', 'edit_posts', 'manage_products'], $definitions['shop_manager']->capabilities);
    }

    #[Test]
    public function addAcceptsObjectInstance(): void
    {
        $this->manager->add(new ShopManagerTestRole());

        $definitions = $this->manager->all();
        self::assertArrayHasKey('shop_manager', $definitions);
    }

    #[Test]
    public function addThrowsWithoutAttribute(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('must have the #[AsRole] attribute');

        $this->manager->add(new class {});
    }

    #[Test]
    public function synchronizeCreatesNewRole(): void
    {
        $this->manager->addDefinition(new RoleDefinition(
            'sync_test_role',
            'Sync Test Role',
            ['read', 'edit_posts'],
        ));

        $this->manager->synchronize();

        $wpRole = get_role('sync_test_role');
        self::assertNotNull($wpRole);
        self::assertTrue($wpRole->has_cap('read'));
        self::assertTrue($wpRole->has_cap('edit_posts'));

        remove_role('sync_test_role');
    }

    #[Test]
    public function synchronizeAddsNewCapabilities(): void
    {
        // Create a role with limited capabilities first
        add_role('sync_update_role', 'Update Role', ['read' => true]);

        $this->manager->addDefinition(new RoleDefinition(
            'sync_update_role',
            'Update Role',
            ['read', 'edit_posts'],
        ));

        $this->manager->synchronize();

        $wpRole = get_role('sync_update_role');
        self::assertNotNull($wpRole);
        self::assertTrue($wpRole->has_cap('read'));
        self::assertTrue($wpRole->has_cap('edit_posts'));

        remove_role('sync_update_role');
    }

    #[Test]
    public function synchronizeRemovesObsoleteCapabilities(): void
    {
        // 1st sync: register with 3 caps to record managed state
        $this->manager->addDefinition(new RoleDefinition(
            'sync_remove_role',
            'Remove Role',
            ['read', 'edit_posts', 'delete_posts'],
        ));
        $this->manager->synchronize();

        $wpRole = get_role('sync_remove_role');
        self::assertNotNull($wpRole);
        self::assertTrue($wpRole->has_cap('delete_posts'));

        // 2nd sync: remove delete_posts from definition
        $manager2 = new RoleManager();
        $manager2->addDefinition(new RoleDefinition(
            'sync_remove_role',
            'Remove Role',
            ['read', 'edit_posts'],
        ));
        $manager2->synchronize();

        $wpRole = get_role('sync_remove_role');
        self::assertNotNull($wpRole);
        self::assertTrue($wpRole->has_cap('read'));
        self::assertTrue($wpRole->has_cap('edit_posts'));
        self::assertFalse($wpRole->has_cap('delete_posts'));

        remove_role('sync_remove_role');
    }

    #[Test]
    public function synchronizeDoesNotRemoveUnmanagedCapabilities(): void
    {
        // 1st sync: register role with managed caps
        $this->manager->addDefinition(new RoleDefinition(
            'plugin_role',
            'Plugin Role',
            ['read', 'edit_posts'],
        ));
        $this->manager->synchronize();

        // Simulate another plugin adding a capability
        $wpRole = get_role('plugin_role');
        self::assertNotNull($wpRole);
        $wpRole->add_cap('manage_woocommerce');

        // 2nd sync: same definitions — unmanaged cap must survive
        $manager2 = new RoleManager();
        $manager2->addDefinition(new RoleDefinition(
            'plugin_role',
            'Plugin Role',
            ['read', 'edit_posts'],
        ));
        $manager2->synchronize();

        $wpRole = get_role('plugin_role');
        self::assertNotNull($wpRole);
        self::assertTrue($wpRole->has_cap('read'));
        self::assertTrue($wpRole->has_cap('edit_posts'));
        self::assertTrue($wpRole->has_cap('manage_woocommerce'));

        remove_role('plugin_role');
    }

    #[Test]
    public function synchronizeRemovesOrphanRole(): void
    {
        // 1st sync: register a role
        $this->manager->addDefinition(new RoleDefinition(
            'orphan_role',
            'Orphan Role',
            ['read'],
        ));
        $this->manager->synchronize();

        self::assertNotNull(get_role('orphan_role'));

        // 2nd sync: empty definitions — orphan role should be removed
        $manager2 = new RoleManager();
        $manager2->synchronize();

        self::assertNull(get_role('orphan_role'));
    }

    #[Test]
    public function synchronizeDoesNotRemoveUnmanagedRoles(): void
    {
        // Create a role not managed by RoleManager
        add_role('external_role', 'External Role', ['read' => true]);

        // Sync with no definitions — external role must survive
        $this->manager->synchronize();

        self::assertNotNull(get_role('external_role'));

        remove_role('external_role');
    }

    #[Test]
    public function synchronizeNeverRemovesBuiltInRoles(): void
    {
        $builtInRoles = ['administrator', 'editor', 'author', 'contributor', 'subscriber'];

        // Poison managed state: pretend we previously managed built-in roles
        $poisonedState = [];
        foreach ($builtInRoles as $role) {
            $poisonedState[$role] = ['read'];
        }
        update_option('wppack_managed_roles', $poisonedState, false);

        // Sync with empty definitions — built-in roles must survive
        $this->manager->synchronize();

        foreach ($builtInRoles as $role) {
            self::assertNotNull(get_role($role), sprintf('Built-in role "%s" was removed', $role));
        }
    }

    #[Test]
    public function synchronizeSavesManagedState(): void
    {
        $this->manager->addDefinition(new RoleDefinition(
            'state_role',
            'State Role',
            ['read', 'edit_posts'],
        ));
        $this->manager->synchronize();

        $state = get_option('wppack_managed_roles', []);
        self::assertArrayHasKey('state_role', $state);
        self::assertSame(['read', 'edit_posts'], $state['state_role']);

        remove_role('state_role');
    }

    #[Test]
    public function unregisterCleansUpManagedState(): void
    {
        $this->manager->addDefinition(new RoleDefinition(
            'cleanup_role',
            'Cleanup Role',
            ['read'],
        ));
        $this->manager->synchronize();

        $state = get_option('wppack_managed_roles', []);
        self::assertArrayHasKey('cleanup_role', $state);

        $this->manager->unregister('cleanup_role');

        $state = get_option('wppack_managed_roles', []);
        self::assertArrayNotHasKey('cleanup_role', $state);
    }

    #[Test]
    public function unregisterDeletesRoleFromWordPressAndDefinitions(): void
    {
        $this->manager->addDefinition(new RoleDefinition(
            'remove_test_role',
            'Remove Test Role',
            ['read'],
        ));
        $this->manager->synchronize();

        self::assertNotNull(get_role('remove_test_role'));

        $this->manager->unregister('remove_test_role');

        self::assertNull(get_role('remove_test_role'));
        self::assertArrayNotHasKey('remove_test_role', $this->manager->all());
    }

    #[Test]
    public function synchronizeUpdatesLabel(): void
    {
        // Create a role with an old label
        add_role('sync_label_role', 'Old Label', ['read' => true]);

        $this->manager->addDefinition(new RoleDefinition(
            'sync_label_role',
            'New Label',
            ['read'],
        ));

        $this->manager->synchronize();

        $wpRoles = wp_roles();
        self::assertSame('New Label', $wpRoles->roles['sync_label_role']['name']);

        remove_role('sync_label_role');
    }

    #[Test]
    public function synchronizeSkipsLabelUpdateWhenUnchanged(): void
    {
        add_role('sync_same_label_role', 'Same Label', ['read' => true]);

        $this->manager->addDefinition(new RoleDefinition(
            'sync_same_label_role',
            'Same Label',
            ['read'],
        ));

        // Should not call update_option when the label is the same
        $this->manager->synchronize();

        $wpRoles = wp_roles();
        self::assertSame('Same Label', $wpRoles->roles['sync_same_label_role']['name']);

        remove_role('sync_same_label_role');
    }

    #[Test]
    public function allReturnsEmptyByDefault(): void
    {
        self::assertSame([], $this->manager->all());
    }

    #[Test]
    public function addDefinitionOverwritesSameName(): void
    {
        $this->manager->addDefinition(new RoleDefinition('test_role', 'Version 1', ['read']));
        $this->manager->addDefinition(new RoleDefinition('test_role', 'Version 2', ['read', 'edit_posts']));

        $definitions = $this->manager->all();
        self::assertCount(1, $definitions);
        self::assertSame('Version 2', $definitions['test_role']->label);
    }
}

#[AsRole(name: 'shop_manager', label: 'Shop Manager', capabilities: ['read', 'edit_posts', 'manage_products'])]
final class ShopManagerTestRole {}
