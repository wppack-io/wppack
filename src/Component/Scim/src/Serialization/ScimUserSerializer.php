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

use WpPack\Component\Scim\Mapping\UserAttributeMapperInterface;
use WpPack\Component\Scim\Schema\ScimConstants;

final readonly class ScimUserSerializer
{
    public function __construct(
        private UserAttributeMapperInterface $mapper,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function serialize(\WP_User $user, string $baseUrl = ''): array
    {
        $scimAttributes = $this->mapper->toScim($user);

        $resource = [
            'schemas' => [ScimConstants::USER_SCHEMA],
            'id' => (string) $user->ID,
        ];

        if ($scimAttributes['externalId'] !== null) {
            $resource['externalId'] = $scimAttributes['externalId'];
        }

        $resource['userName'] = $scimAttributes['userName'];
        $resource['name'] = $scimAttributes['name'];
        $resource['displayName'] = $scimAttributes['displayName'];

        if ($scimAttributes['nickName'] !== '') {
            $resource['nickName'] = $scimAttributes['nickName'];
        }

        if ($scimAttributes['profileUrl'] !== '') {
            $resource['profileUrl'] = $scimAttributes['profileUrl'];
        }

        $resource['emails'] = $scimAttributes['emails'];
        $resource['active'] = $scimAttributes['active'];

        if ($scimAttributes['locale'] !== null) {
            $resource['locale'] = $scimAttributes['locale'];
        }

        if ($scimAttributes['timezone'] !== null) {
            $resource['timezone'] = $scimAttributes['timezone'];
        }

        if ($scimAttributes['title'] !== null) {
            $resource['title'] = $scimAttributes['title'];
        }

        $resource['groups'] = $this->serializeGroups($user);

        $resource['meta'] = [
            'resourceType' => 'User',
            'created' => $user->user_registered,
            'lastModified' => $user->user_registered,
            'location' => $baseUrl . '/scim/v2/Users/' . $user->ID,
        ];

        return $resource;
    }

    /**
     * @return list<array<string, string>>
     */
    private function serializeGroups(\WP_User $user): array
    {
        $groups = [];

        foreach ($user->roles as $role) {
            $groups[] = [
                'value' => $role,
                'display' => wp_roles()->get_names()[$role] ?? $role,
                '$ref' => '/scim/v2/Groups/' . $role,
            ];
        }

        return $groups;
    }
}
