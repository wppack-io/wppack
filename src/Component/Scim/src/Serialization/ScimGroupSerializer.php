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

namespace WpPack\Component\Scim\Serialization;

use WpPack\Component\Scim\Mapping\GroupMapperInterface;
use WpPack\Component\Scim\Schema\ScimConstants;

final readonly class ScimGroupSerializer
{
    public function __construct(
        private GroupMapperInterface $mapper,
    ) {}

    /**
     * @param array{name: string, capabilities: array<string, bool>} $role
     * @param list<\WP_User> $members
     *
     * @return array<string, mixed>
     */
    public function serialize(string $roleName, array $role, array $members, string $baseUrl = ''): array
    {
        $scimAttributes = $this->mapper->toScim($roleName, $role, $members, $baseUrl);

        return [
            'schemas' => [ScimConstants::GROUP_SCHEMA],
            'id' => $scimAttributes['id'],
            'displayName' => $scimAttributes['displayName'],
            'members' => $scimAttributes['members'],
            'meta' => [
                'resourceType' => 'Group',
                'location' => $baseUrl . '/scim/v2/Groups/' . $roleName,
            ],
        ];
    }
}
