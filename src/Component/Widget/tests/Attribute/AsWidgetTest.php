<?php

/*
 * This file is part of the WPPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WPPack\Component\Widget\Tests\Attribute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Widget\Attribute\AsWidget;

#[CoversClass(AsWidget::class)]
final class AsWidgetTest extends TestCase
{
    #[Test]
    public function storesAllFields(): void
    {
        $attr = new AsWidget(id: 'featured', label: 'Featured', description: 'Shows featured posts');

        self::assertSame('featured', $attr->id);
        self::assertSame('Featured', $attr->label);
        self::assertSame('Shows featured posts', $attr->description);
    }

    #[Test]
    public function descriptionDefaultsToEmpty(): void
    {
        $attr = new AsWidget(id: 'x', label: 'X');

        self::assertSame('', $attr->description);
    }

    #[Test]
    public function targetsClassesOnly(): void
    {
        $ref = new \ReflectionClass(AsWidget::class);
        $attribute = $ref->getAttributes(\Attribute::class)[0] ?? null;

        self::assertNotNull($attribute);
        self::assertSame(\Attribute::TARGET_CLASS, $attribute->getArguments()[0]);
    }
}
