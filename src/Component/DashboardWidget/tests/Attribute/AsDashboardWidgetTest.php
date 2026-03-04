<?php

declare(strict_types=1);

namespace WpPack\Component\DashboardWidget\Tests\Attribute;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\DashboardWidget\Attribute\AsDashboardWidget;

final class AsDashboardWidgetTest extends TestCase
{
    #[Test]
    public function defaultValues(): void
    {
        $attribute = new AsDashboardWidget(id: 'test_widget', title: 'Test Widget');

        self::assertSame('test_widget', $attribute->id);
        self::assertSame('Test Widget', $attribute->title);
        self::assertNull($attribute->capability);
        self::assertSame('normal', $attribute->context);
        self::assertSame('core', $attribute->priority);
    }

    #[Test]
    public function allParametersSpecified(): void
    {
        $attribute = new AsDashboardWidget(
            id: 'custom_widget',
            title: 'Custom Widget',
            capability: 'manage_options',
            context: 'side',
            priority: 'high',
        );

        self::assertSame('custom_widget', $attribute->id);
        self::assertSame('Custom Widget', $attribute->title);
        self::assertSame('manage_options', $attribute->capability);
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
