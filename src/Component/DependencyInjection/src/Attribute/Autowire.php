<?php

declare(strict_types=1);

namespace WpPack\Component\DependencyInjection\Attribute;

#[\Attribute(\Attribute::TARGET_PARAMETER)]
final class Autowire
{
    public function __construct(
        public readonly ?string $env = null,
        public readonly ?string $param = null,
        public readonly ?string $service = null,
    ) {}
}
