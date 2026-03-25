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
use WpPack\Component\SiteHealth\Attribute\AsDebugInfo;

final class AsDebugInfoTest extends TestCase
{
    #[Test]
    public function constructWithRequiredParameters(): void
    {
        $attribute = new AsDebugInfo(
            section: 'wp-server',
            label: 'Server Info',
        );

        self::assertSame('wp-server', $attribute->section);
        self::assertSame('Server Info', $attribute->label);
        self::assertNull($attribute->description);
        self::assertFalse($attribute->showCount);
        self::assertFalse($attribute->private);
    }

    #[Test]
    public function constructWithAllParameters(): void
    {
        $attribute = new AsDebugInfo(
            section: 'custom-section',
            label: 'Custom Section',
            description: 'Detailed description of the section.',
            showCount: true,
            private: true,
        );

        self::assertSame('custom-section', $attribute->section);
        self::assertSame('Custom Section', $attribute->label);
        self::assertSame('Detailed description of the section.', $attribute->description);
        self::assertTrue($attribute->showCount);
        self::assertTrue($attribute->private);
    }

    #[Test]
    public function isTargetClassAttribute(): void
    {
        $reflection = new \ReflectionClass(AsDebugInfo::class);
        $attributes = $reflection->getAttributes(\Attribute::class);

        self::assertCount(1, $attributes);

        $attributeInstance = $attributes[0]->newInstance();
        self::assertSame(\Attribute::TARGET_CLASS, $attributeInstance->flags);
    }
}
