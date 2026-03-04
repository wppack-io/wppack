<?php

declare(strict_types=1);

namespace WpPack\Component\Translation\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class ThemeTextDomain
{
    public function __construct(
        public readonly string $domain,
        public readonly string $path = 'languages',
    ) {}
}
