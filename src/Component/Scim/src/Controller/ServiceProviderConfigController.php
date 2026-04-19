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
use WPPack\Component\Scim\Schema\ScimConstants;
use WPPack\Component\Scim\Schema\ServiceProviderConfig;

#[RestRoute(namespace: 'scim/v2', route: '/ServiceProviderConfig', methods: [HttpMethod::GET])]
#[IsGranted(ScimConstants::CAPABILITY_PROVISION)]
final class ServiceProviderConfigController extends AbstractRestController
{
    public function __construct(
        private readonly ServiceProviderConfig $config,
        private readonly string $baseUrl = '',
    ) {}

    public function __invoke(): JsonResponse
    {
        return $this->json($this->config->toArray($this->baseUrl), headers: ['Content-Type' => ScimConstants::CONTENT_TYPE]);
    }
}
