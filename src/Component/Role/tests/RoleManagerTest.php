<?php

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
        foreach ($this->manager->getDefinitions() as $definition) {
            remove_role($definition->name);
        }
    }

    #[Test]
    public function addDefinitionStoresDefinition(): void
    {
        $definition = new RoleDefinition('test_role', 'Test Role', ['read']);

        $this->manager->addDefinition($definition);

        $definitions = $this->manager->getDefinitions();
        self::assertCount(1, $definitions);
        self::assertArrayHasKey('test_role', $definitions);
        self::assertSame('Test Role', $definitions['test_role']->label);
        self::assertSame(['read'], $definitions['test_role']->capabilities);
    }

    #[Test]
    public function addResolvesFromAsRoleAttribute(): void
    {
        $this->manager->add(ShopManagerTestRole::class);

        $definitions = $this->manager->getDefinitions();
        self::assertCount(1, $definitions);
        self::assertArrayHasKey('shop_manager', $definitions);
        self::assertSame('Shop Manager', $definitions['shop_manager']->label);
        self::assertSame(['read', 'edit_posts', 'manage_products'], $definitions['shop_manager']->capabilities);
    }

    #[Test]
    public function addAcceptsObjectInstance(): void
    {
        $this->manager->add(new ShopManagerTestRole());

        $definitions = $this->manager->getDefinitions();
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
        // Create a role with extra capabilities
        add_role('sync_remove_role', 'Remove Role', ['read' => true, 'edit_posts' => true, 'delete_posts' => true]);

        $this->manager->addDefinition(new RoleDefinition(
            'sync_remove_role',
            'Remove Role',
            ['read', 'edit_posts'],
        ));

        $this->manager->synchronize();

        $wpRole = get_role('sync_remove_role');
        self::assertNotNull($wpRole);
        self::assertTrue($wpRole->has_cap('read'));
        self::assertTrue($wpRole->has_cap('edit_posts'));
        self::assertFalse($wpRole->has_cap('delete_posts'));

        remove_role('sync_remove_role');
    }

    #[Test]
    public function removeDeletesRoleFromWordPressAndDefinitions(): void
    {
        $this->manager->addDefinition(new RoleDefinition(
            'remove_test_role',
            'Remove Test Role',
            ['read'],
        ));
        $this->manager->synchronize();

        self::assertNotNull(get_role('remove_test_role'));

        $this->manager->remove('remove_test_role');

        self::assertNull(get_role('remove_test_role'));
        self::assertArrayNotHasKey('remove_test_role', $this->manager->getDefinitions());
    }

    #[Test]
    public function getDefinitionsReturnsEmptyByDefault(): void
    {
        self::assertSame([], $this->manager->getDefinitions());
    }

    #[Test]
    public function addDefinitionOverwritesSameName(): void
    {
        $this->manager->addDefinition(new RoleDefinition('test_role', 'Version 1', ['read']));
        $this->manager->addDefinition(new RoleDefinition('test_role', 'Version 2', ['read', 'edit_posts']));

        $definitions = $this->manager->getDefinitions();
        self::assertCount(1, $definitions);
        self::assertSame('Version 2', $definitions['test_role']->label);
    }
}

#[AsRole(name: 'shop_manager', label: 'Shop Manager', capabilities: ['read', 'edit_posts', 'manage_products'])]
final class ShopManagerTestRole {}
