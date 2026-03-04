<?php

declare(strict_types=1);

namespace WpPack\Component\Shortcode\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class AsShortcode
{
    public function __construct(
        public readonly string $name,
        public readonly string $description = '',
    ) {}
}
