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
use WpPack\Component\Scim\Serialization\ListResponseSerializer;

#[RestRoute(namespace: 'scim/v2', route: '/Schemas')]
#[IsGranted('scim_provision')]
final class SchemaController extends AbstractRestController
{
    #[RestRoute(methods: [HttpMethod::GET])]
    public function list(): JsonResponse
    {
        $schemas = [
            UserSchema::definition()->toArray(),
            GroupSchema::definition()->toArray(),
        ];

        return $this->json(
            ListResponseSerializer::serialize($schemas, \count($schemas)),
            headers: ['Content-Type' => ScimConstants::CONTENT_TYPE],
        );
    }

    #[RestRoute(route: '/{id}', methods: [HttpMethod::GET])]
    public function get(string $id): JsonResponse
    {
        $schema = match ($id) {
            ScimConstants::USER_SCHEMA => UserSchema::definition(),
            ScimConstants::GROUP_SCHEMA => GroupSchema::definition(),
            default => null,
        };

        if ($schema === null) {
            return $this->json(
                ['schemas' => [ScimConstants::ERROR_SCHEMA], 'status' => '404', 'detail' => 'Schema not found.'],
                404,
                ['Content-Type' => ScimConstants::CONTENT_TYPE],
            );
        }

        return $this->json($schema->toArray(), headers: ['Content-Type' => ScimConstants::CONTENT_TYPE]);
    }
}
