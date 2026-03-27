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
use WpPack\Component\Scim\Schema\ScimConstants;
use WpPack\Component\Scim\Schema\ServiceProviderConfig;

#[RestRoute(namespace: 'scim/v2', route: '/ServiceProviderConfig', methods: [HttpMethod::GET])]
#[IsGranted('scim_provision')]
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
