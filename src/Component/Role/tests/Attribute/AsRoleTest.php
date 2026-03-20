<?php

declare(strict_types=1);

namespace WpPack\Component\Role\Tests\Attribute;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Role\Attribute\AsRole;

final class AsRoleTest extends TestCase
{
    #[Test]
    public function allParametersSet(): void
    {
        $attr = new AsRole(
            name: 'shop_manager',
            label: 'Shop Manager',
            capabilities: ['read', 'edit_posts', 'manage_products'],
        );

        self::assertSame('shop_manager', $attr->name);
        self::assertSame('Shop Manager', $attr->label);
        self::assertSame(['read', 'edit_posts', 'manage_products'], $attr->capabilities);
    }

    #[Test]
    public function capabilitiesDefaultsToEmpty(): void
    {
        $attr = new AsRole(name: 'viewer', label: 'Viewer');

        self::assertSame([], $attr->capabilities);
    }

    #[Test]
    public function targetsClassOnly(): void
    {
        $reflection = new \ReflectionClass(AsRole::class);
        $attributes = $reflection->getAttributes(\Attribute::class);

        self::assertCount(1, $attributes);
        $flags = $attributes[0]->newInstance()->flags;

        self::assertNotSame(0, $flags & \Attribute::TARGET_CLASS);
        self::assertSame(0, $flags & \Attribute::TARGET_METHOD);
        self::assertSame(0, $flags & \Attribute::IS_REPEATABLE);
    }

    #[Test]
    public function canBeAppliedToClass(): void
    {
        $class = new #[AsRole(name: 'editor_role', label: 'Editor Role', capabilities: ['read', 'edit_posts'])] class {};

        $reflection = new \ReflectionClass($class);
        $attributes = $reflection->getAttributes(AsRole::class);

        self::assertCount(1, $attributes);
        $instance = $attributes[0]->newInstance();
        self::assertSame('editor_role', $instance->name);
        self::assertSame('Editor Role', $instance->label);
        self::assertSame(['read', 'edit_posts'], $instance->capabilities);
    }
}
