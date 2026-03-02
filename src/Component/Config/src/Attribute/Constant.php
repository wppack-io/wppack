<?php

declare(strict_types=1);

namespace WpPack\Component\Config\Attribute;

#[\Attribute(\Attribute::TARGET_PARAMETER)]
final class Constant
{
    public function __construct(
        public readonly string $name,
    ) {}
}
