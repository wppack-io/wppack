<?php

declare(strict_types=1);

namespace WpPack\Component\Rest\Tests\Attribute;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Rest\Attribute\Permission;

final class PermissionTest extends TestCase
{
    #[Test]
    public function capabilityPermission(): void
    {
        $permission = new Permission(capability: 'edit_posts');

        self::assertSame('edit_posts', $permission->capability);
        self::assertNull($permission->callback);
        self::assertFalse($permission->public);
    }

    #[Test]
    public function callbackPermission(): void
    {
        $permission = new Permission(callback: 'canDelete');

        self::assertNull($permission->capability);
        self::assertSame('canDelete', $permission->callback);
        self::assertFalse($permission->public);
    }

    #[Test]
    public function publicPermission(): void
    {
        $permission = new Permission(public: true);

        self::assertNull($permission->capability);
        self::assertNull($permission->callback);
        self::assertTrue($permission->public);
    }

    #[Test]
    public function throwsWhenBothCapabilityAndCallback(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('cannot have both');

        new Permission(capability: 'edit_posts', callback: 'canEdit');
    }

    #[Test]
    public function throwsWhenNoneSet(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('must have');

        new Permission();
    }

    #[Test]
    public function targetsClassAndMethod(): void
    {
        $reflection = new \ReflectionClass(Permission::class);
        $attributes = $reflection->getAttributes(\Attribute::class);
        $flags = $attributes[0]->newInstance()->flags;

        self::assertNotSame(0, $flags & \Attribute::TARGET_CLASS);
        self::assertNotSame(0, $flags & \Attribute::TARGET_METHOD);
    }

    #[Test]
    public function isNotRepeatable(): void
    {
        $reflection = new \ReflectionClass(Permission::class);
        $attributes = $reflection->getAttributes(\Attribute::class);
        $flags = $attributes[0]->newInstance()->flags;

        self::assertSame(0, $flags & \Attribute::IS_REPEATABLE);
    }
}
