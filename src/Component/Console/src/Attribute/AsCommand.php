<?php

declare(strict_types=1);

namespace WpPack\Component\Console\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class AsCommand
{
    public function __construct(
        public readonly string $name,
        public readonly string $description = '',
        public readonly string $usage = '',
    ) {}
}
