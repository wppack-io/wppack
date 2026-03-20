<?php

declare(strict_types=1);

namespace WpPack\Component\DashboardWidget\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class AsDashboardWidget
{
    public function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly string $context = 'normal',
        public readonly string $priority = 'core',
    ) {}
}
