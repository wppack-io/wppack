<?php

declare(strict_types=1);

namespace WpPack\Component\DependencyInjection\Tests\Attribute;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\DependencyInjection\Attribute\AsService;

final class AsServiceTest extends TestCase
{
    #[Test]
    public function defaultValues(): void
    {
        $attr = new AsService();

        self::assertTrue($attr->public);
        self::assertFalse($attr->lazy);
        self::assertSame([], $attr->tags);
        self::assertTrue($attr->autowire);
    }

    #[Test]
    public function customValues(): void
    {
        $attr = new AsService(
            public: false,
            lazy: true,
            tags: ['app.handler', 'kernel.listener'],
            autowire: false,
        );

        self::assertFalse($attr->public);
        self::assertTrue($attr->lazy);
        self::assertSame(['app.handler', 'kernel.listener'], $attr->tags);
        self::assertFalse($attr->autowire);
    }

    #[Test]
    public function parsesFromReflection(): void
    {
        $reflection = new \ReflectionClass(Fixtures\AnnotatedService::class);
        $attributes = $reflection->getAttributes(AsService::class);

        self::assertCount(1, $attributes);

        /** @var AsService $instance */
        $instance = $attributes[0]->newInstance();
        self::assertTrue($instance->public);
        self::assertTrue($instance->lazy);
        self::assertSame(['test.tag'], $instance->tags);
    }
}
