<?php
/*
 * This file is part of the WpPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
