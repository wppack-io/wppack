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

namespace WpPack\Component\Scim\Controller;

use WpPack\Component\HttpFoundation\JsonResponse;
use WpPack\Component\Rest\AbstractRestController;
use WpPack\Component\Rest\Attribute\RestRoute;
use WpPack\Component\Rest\HttpMethod;
use WpPack\Component\Role\Attribute\IsGranted;
use WpPack\Component\Scim\Schema\GroupSchema;
use WpPack\Component\Scim\Schema\ScimConstants;
use WpPack\Component\Scim\Schema\UserSchema;
use WpPack\Component\Scim\Serialization\ErrorSerializer;
use WpPack\Component\Scim\Serialization\ListResponseSerializer;

#[RestRoute(namespace: 'scim/v2', route: '/ResourceTypes')]
#[IsGranted(ScimConstants::CAPABILITY_PROVISION)]
final class ResourceTypeController extends AbstractRestController
{
    public function __construct(
        private readonly string $baseUrl = '',
    ) {}

    #[RestRoute(methods: [HttpMethod::GET])]
    public function list(): JsonResponse
    {
        $types = [
            UserSchema::resourceType()->toArray($this->baseUrl),
            GroupSchema::resourceType()->toArray($this->baseUrl),
        ];

        return $this->json(
            ListResponseSerializer::serialize($types, \count($types)),
            headers: ['Content-Type' => ScimConstants::CONTENT_TYPE],
        );
    }

    #[RestRoute(route: '/{id}', methods: [HttpMethod::GET])]
    public function get(string $id): JsonResponse
    {
        $type = match ($id) {
            'User' => UserSchema::resourceType(),
            'Group' => GroupSchema::resourceType(),
            default => null,
        };

        if ($type === null) {
            return $this->json(
                ErrorSerializer::fromMessage('ResourceType not found.', 404),
                404,
                ['Content-Type' => ScimConstants::CONTENT_TYPE],
            );
        }

        return $this->json($type->toArray($this->baseUrl), headers: ['Content-Type' => ScimConstants::CONTENT_TYPE]);
    }
}
