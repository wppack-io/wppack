<?php

declare(strict_types=1);

namespace WpPack\Component\DependencyInjection\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class AsService
{
    /**
     * @param list<string> $tags
     */
    public function __construct(
        public readonly bool $public = true,
        public readonly bool $lazy = false,
        public readonly array $tags = [],
        public readonly bool $autowire = true,
    ) {}
}
