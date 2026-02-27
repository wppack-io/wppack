<?php

declare(strict_types=1);

namespace WpPack\Component\SiteHealth\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class AsHealthCheck
{
    public function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly string $category,
        public readonly bool $async = false,
    ) {}
}
