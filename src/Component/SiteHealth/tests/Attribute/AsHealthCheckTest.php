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

namespace WpPack\Component\SiteHealth\Tests\Attribute;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\SiteHealth\Attribute\AsHealthCheck;

final class AsHealthCheckTest extends TestCase
{
    #[Test]
    public function constructWithRequiredParameters(): void
    {
        $attribute = new AsHealthCheck(
            id: 'php_version',
            label: 'PHP Version',
            category: 'direct',
        );

        self::assertSame('php_version', $attribute->id);
        self::assertSame('PHP Version', $attribute->label);
        self::assertSame('direct', $attribute->category);
        self::assertFalse($attribute->async);
    }

    #[Test]
    public function constructWithAsyncEnabled(): void
    {
        $attribute = new AsHealthCheck(
            id: 'background_check',
            label: 'Background Check',
            category: 'async',
            async: true,
        );

        self::assertTrue($attribute->async);
    }

    #[Test]
    public function isTargetClassAttribute(): void
    {
        $reflection = new \ReflectionClass(AsHealthCheck::class);
        $attributes = $reflection->getAttributes(\Attribute::class);

        self::assertCount(1, $attributes);

        $attributeInstance = $attributes[0]->newInstance();
        self::assertSame(\Attribute::TARGET_CLASS, $attributeInstance->flags);
    }
}
