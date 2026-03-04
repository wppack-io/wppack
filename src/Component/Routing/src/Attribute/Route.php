<?php

declare(strict_types=1);

namespace WpPack\Component\Routing\Attribute;

use WpPack\Component\Routing\RoutePosition;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
final class Route
{
    public function __construct(
        public readonly string $name,
        public readonly string $regex,
        public readonly string $query,
        public readonly RoutePosition $position = RoutePosition::Top,
    ) {}
}
