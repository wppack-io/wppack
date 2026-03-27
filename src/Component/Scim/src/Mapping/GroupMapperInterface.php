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

namespace WpPack\Component\Scim\Mapping;

interface GroupMapperInterface
{
    /**
     * Map a WordPress role to SCIM group attributes.
     *
     * @param array{name: string, capabilities: array<string, bool>} $role
     * @param list<\WP_User> $members
     *
     * @return array<string, mixed>
     */
    public function toScim(string $roleName, array $role, array $members, string $baseUrl = ''): array;
}
