<?php

declare(strict_types=1);

namespace WpPack\Component\DependencyInjection\Attribute;

#[\Attribute(\Attribute::TARGET_PARAMETER)]
class Autowire
{
    public function __construct(
        public readonly ?string $env = null,
        public readonly ?string $param = null,
        public readonly ?string $service = null,
        public readonly ?string $option = null,
        public readonly ?string $constant = null,
    ) {}
}
