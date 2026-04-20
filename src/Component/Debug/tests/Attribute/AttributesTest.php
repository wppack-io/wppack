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

namespace WPPack\Component\Debug\Tests\Attribute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Debug\Attribute\AsDataCollector;
use WPPack\Component\Debug\Attribute\AsPanelRenderer;

#[CoversClass(AsDataCollector::class)]
#[CoversClass(AsPanelRenderer::class)]
final class AttributesTest extends TestCase
{
    #[Test]
    public function asDataCollectorStoresNameAndPriority(): void
    {
        $attr = new AsDataCollector(name: 'db', priority: 85);

        self::assertSame('db', $attr->name);
        self::assertSame(85, $attr->priority);
    }

    #[Test]
    public function asDataCollectorDefaultsPriorityToZero(): void
    {
        $attr = new AsDataCollector(name: 'env');

        self::assertSame(0, $attr->priority);
    }

    #[Test]
    public function asDataCollectorTargetsClassesOnly(): void
    {
        $ref = new \ReflectionClass(AsDataCollector::class);
        $flags = $ref->getAttributes(\Attribute::class)[0]->getArguments()[0];

        self::assertSame(\Attribute::TARGET_CLASS, $flags);
    }

    #[Test]
    public function asPanelRendererStoresNameAndPriority(): void
    {
        $attr = new AsPanelRenderer(name: 'database', priority: 10);

        self::assertSame('database', $attr->name);
        self::assertSame(10, $attr->priority);
    }

    #[Test]
    public function asPanelRendererDefaultsPriorityToZero(): void
    {
        $attr = new AsPanelRenderer(name: 'event');

        self::assertSame(0, $attr->priority);
    }

    #[Test]
    public function asPanelRendererTargetsClassesOnly(): void
    {
        $ref = new \ReflectionClass(AsPanelRenderer::class);
        $flags = $ref->getAttributes(\Attribute::class)[0]->getArguments()[0];

        self::assertSame(\Attribute::TARGET_CLASS, $flags);
    }
}
