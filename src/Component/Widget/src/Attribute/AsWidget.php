<?php

declare(strict_types=1);

namespace WpPack\Component\Widget\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class AsWidget
{
    public function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly string $description = '',
    ) {}
}
