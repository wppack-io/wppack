<?php

declare(strict_types=1);

namespace WpPack\Component\Routing\Attribute;

use WpPack\Component\Routing\RoutePosition;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
final class Route
{
    public function __construct(
        public readonly string $path,
        public readonly string $name = '',
        /** @var array<string, string> */
        public readonly array $requirements = [],
        /** @var array<string, string> */
        public readonly array $vars = [],
        public readonly RoutePosition $position = RoutePosition::Top,
    ) {}
}
