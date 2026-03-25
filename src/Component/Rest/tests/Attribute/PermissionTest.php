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

namespace WpPack\Component\Rest\Tests\Attribute;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Rest\Attribute\Permission;

final class PermissionTest extends TestCase
{
    #[Test]
    public function callbackPermission(): void
    {
        $permission = new Permission(callback: 'canDelete');

        self::assertSame('canDelete', $permission->callback);
        self::assertFalse($permission->public);
    }

    #[Test]
    public function publicPermission(): void
    {
        $permission = new Permission(public: true);

        self::assertNull($permission->callback);
        self::assertTrue($permission->public);
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
