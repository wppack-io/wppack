<?php

declare(strict_types=1);

namespace WpPack\Component\Role;

final readonly class RoleDefinition
{
    /**
     * @param list<string> $capabilities
     */
    public function __construct(
        public string $name,
        public string $label,
        public array $capabilities,
    ) {}
}
