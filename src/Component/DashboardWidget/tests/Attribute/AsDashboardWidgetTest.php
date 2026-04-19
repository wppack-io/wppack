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

namespace WPPack\Component\DashboardWidget\Tests\Attribute;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\DashboardWidget\Attribute\AsDashboardWidget;

final class AsDashboardWidgetTest extends TestCase
{
    #[Test]
    public function defaultValues(): void
    {
        $attribute = new AsDashboardWidget(id: 'test_widget', label: 'Test Widget');

        self::assertSame('test_widget', $attribute->id);
        self::assertSame('Test Widget', $attribute->label);
        self::assertSame('normal', $attribute->context);
        self::assertSame('core', $attribute->priority);
    }

    #[Test]
    public function allParametersSpecified(): void
    {
        $attribute = new AsDashboardWidget(
            id: 'custom_widget',
            label: 'Custom Widget',
            context: 'side',
            priority: 'high',
        );

        self::assertSame('custom_widget', $attribute->id);
        self::assertSame('Custom Widget', $attribute->label);
        self::assertSame('side', $attribute->context);
        self::assertSame('high', $attribute->priority);
    }

    #[Test]
    public function isTargetClass(): void
    {
        $reflection = new \ReflectionClass(AsDashboardWidget::class);
        $attributes = $reflection->getAttributes(\Attribute::class);

        self::assertCount(1, $attributes);

        $attr = $attributes[0]->newInstance();
        self::assertSame(\Attribute::TARGET_CLASS, $attr->flags);
    }
}
