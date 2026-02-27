<?php

declare(strict_types=1);

namespace WpPack\Component\SiteHealth\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class AsDebugInfo
{
    public function __construct(
        public readonly string $section,
        public readonly string $label,
        public readonly ?string $description = null,
        public readonly bool $showCount = false,
        public readonly bool $private = false,
    ) {}
}
