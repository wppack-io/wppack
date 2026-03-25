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

namespace WpPack\Component\Shortcode\Tests\Attribute;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Shortcode\Attribute\AsShortcode;

final class AsShortcodeTest extends TestCase
{
    #[Test]
    public function defaultValues(): void
    {
        $attribute = new AsShortcode(name: 'my_shortcode');

        self::assertSame('my_shortcode', $attribute->name);
        self::assertSame('', $attribute->description);
    }

    #[Test]
    public function allParametersSpecified(): void
    {
        $attribute = new AsShortcode(
            name: 'gallery',
            description: 'Display an image gallery',
        );

        self::assertSame('gallery', $attribute->name);
        self::assertSame('Display an image gallery', $attribute->description);
    }

    #[Test]
    public function isTargetClass(): void
    {
        $reflection = new \ReflectionClass(AsShortcode::class);
        $attributes = $reflection->getAttributes(\Attribute::class);

        self::assertCount(1, $attributes);

        $attr = $attributes[0]->newInstance();
        self::assertSame(\Attribute::TARGET_CLASS, $attr->flags);
    }
}
