<?php

declare(strict_types=1);

namespace WpPack\Component\Role\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class AsRole
{
    /**
     * @param list<string> $capabilities
     */
    public function __construct(
        public readonly string $name,
        public readonly string $label,
        public readonly array $capabilities = [],
    ) {}
}
