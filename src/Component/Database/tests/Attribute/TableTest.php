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

namespace WpPack\Component\Database\Tests\Attribute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Database\Attribute\Table;

#[CoversClass(Table::class)]
final class TableTest extends TestCase
{
    #[Test]
    public function storesTableName(): void
    {
        $table = new Table('analytics');

        self::assertSame('analytics', $table->name);
    }

    #[Test]
    public function isTargetClassAttribute(): void
    {
        $ref = new \ReflectionClass(Table::class);
        $attributes = $ref->getAttributes(\Attribute::class);

        self::assertCount(1, $attributes);

        $attribute = $attributes[0]->newInstance();
        self::assertSame(\Attribute::TARGET_CLASS, $attribute->flags);
    }

    #[Test]
    public function canBeReadFromClass(): void
    {
        $class = new #[Table('test_table')] class {};

        $ref = new \ReflectionClass($class);
        $attributes = $ref->getAttributes(Table::class);

        self::assertCount(1, $attributes);
        self::assertSame('test_table', $attributes[0]->newInstance()->name);
    }
}
