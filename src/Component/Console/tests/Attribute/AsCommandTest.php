<?php

declare(strict_types=1);

namespace WpPack\Component\Console\Tests\Attribute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Console\Attribute\AsCommand;

#[CoversClass(AsCommand::class)]
final class AsCommandTest extends TestCase
{
    #[Test]
    public function constructWithAllParameters(): void
    {
        $attribute = new AsCommand(
            name: 'cache clear',
            description: 'Clear all caches',
            usage: 'wp cache clear --type=all',
        );

        self::assertSame('cache clear', $attribute->name);
        self::assertSame('Clear all caches', $attribute->description);
        self::assertSame('wp cache clear --type=all', $attribute->usage);
    }

    #[Test]
    public function constructWithDefaults(): void
    {
        $attribute = new AsCommand(name: 'test');

        self::assertSame('test', $attribute->name);
        self::assertSame('', $attribute->description);
        self::assertSame('', $attribute->usage);
    }

    #[Test]
    public function isAttributeClass(): void
    {
        $ref = new \ReflectionClass(AsCommand::class);
        $attributes = $ref->getAttributes(\Attribute::class);

        self::assertCount(1, $attributes);

        $attr = $attributes[0]->newInstance();
        self::assertSame(\Attribute::TARGET_CLASS, $attr->flags);
    }

    #[Test]
    public function propertiesAreReadonly(): void
    {
        $ref = new \ReflectionClass(AsCommand::class);

        self::assertTrue($ref->getProperty('name')->isReadonly());
        self::assertTrue($ref->getProperty('description')->isReadonly());
        self::assertTrue($ref->getProperty('usage')->isReadonly());
    }
}
