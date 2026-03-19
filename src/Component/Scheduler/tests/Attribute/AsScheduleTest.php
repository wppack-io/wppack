<?php

declare(strict_types=1);

namespace WpPack\Component\Scheduler\Tests\Attribute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Scheduler\Attribute\AsSchedule;

#[CoversClass(AsSchedule::class)]
final class AsScheduleTest extends TestCase
{
    #[Test]
    public function defaultNameIsDefault(): void
    {
        $attribute = new AsSchedule();

        self::assertSame('default', $attribute->name);
    }

    #[Test]
    public function customName(): void
    {
        $attribute = new AsSchedule('custom-schedule');

        self::assertSame('custom-schedule', $attribute->name);
    }

    #[Test]
    public function isPhpAttribute(): void
    {
        $reflection = new \ReflectionClass(AsSchedule::class);
        $attributes = $reflection->getAttributes(\Attribute::class);

        self::assertCount(1, $attributes);

        $instance = $attributes[0]->newInstance();
        self::assertSame(\Attribute::TARGET_CLASS, $instance->flags);
    }

    #[Test]
    public function canBeRetrievedFromClass(): void
    {
        $class = new #[AsSchedule('my-schedule')] class {};

        $reflection = new \ReflectionClass($class);
        $attributes = $reflection->getAttributes(AsSchedule::class);

        self::assertCount(1, $attributes);

        $instance = $attributes[0]->newInstance();
        self::assertSame('my-schedule', $instance->name);
    }

    #[Test]
    public function namePropertyIsReadonly(): void
    {
        $reflection = new \ReflectionProperty(AsSchedule::class, 'name');

        self::assertTrue($reflection->isReadOnly());
    }
}
