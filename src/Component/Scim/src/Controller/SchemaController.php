<?php

/*
 * This file is part of the WPPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WPPack\Component\Scim\Controller;

use WPPack\Component\HttpFoundation\JsonResponse;
use WPPack\Component\Rest\AbstractRestController;
use WPPack\Component\Rest\Attribute\RestRoute;
use WPPack\Component\Rest\HttpMethod;
use WPPack\Component\Role\Attribute\IsGranted;
use WPPack\Component\Scim\Schema\GroupSchema;
use WPPack\Component\Scim\Schema\ScimConstants;
use WPPack\Component\Scim\Schema\UserSchema;
use WPPack\Component\Scim\Serialization\ErrorSerializer;
use WPPack\Component\Scim\Serialization\ListResponseSerializer;

#[RestRoute(namespace: 'scim/v2', route: '/Schemas')]
#[IsGranted(ScimConstants::CAPABILITY_PROVISION)]
final class SchemaController extends AbstractRestController
{
    public function __construct(
        private readonly string $baseUrl = '',
    ) {}

    #[RestRoute(methods: [HttpMethod::GET])]
    public function list(): JsonResponse
    {
        $schemas = [
            UserSchema::definition()->toArray($this->baseUrl),
            GroupSchema::definition()->toArray($this->baseUrl),
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
                ErrorSerializer::fromMessage('Schema not found.', 404),
                404,
                ['Content-Type' => ScimConstants::CONTENT_TYPE],
            );
        }

        return $this->json($schema->toArray($this->baseUrl), headers: ['Content-Type' => ScimConstants::CONTENT_TYPE]);
    }
}
