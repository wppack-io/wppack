<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class AsDataCollector
{
    public function __construct(
        public readonly string $name,
        public readonly int $priority = 0,
    ) {}
}
