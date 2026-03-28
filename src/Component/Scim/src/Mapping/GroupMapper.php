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

final readonly class GroupMapper implements GroupMapperInterface
{
    /**
     * @param array{name: string, capabilities: array<string, bool>} $role
     * @param list<\WP_User> $members
     *
     * @return array<string, mixed>
     */
    public function toScim(string $roleName, array $role, array $members, string $baseUrl = ''): array
    {
        $memberList = [];
        foreach ($members as $user) {
            $memberList[] = [
                'value' => (string) $user->ID,
                'display' => $user->display_name,
                '$ref' => $baseUrl . '/scim/v2/Users/' . $user->ID,
            ];
        }

        return [
            'id' => $roleName,
            'displayName' => $role['name'],
            'members' => $memberList,
        ];
    }
}
