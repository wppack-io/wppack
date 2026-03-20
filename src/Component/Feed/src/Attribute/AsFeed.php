<?php

declare(strict_types=1);

namespace WpPack\Component\Feed\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class AsFeed
{
    public function __construct(
        public readonly string $slug,
        public readonly string $label = '',
    ) {}
}
