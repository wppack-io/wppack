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

namespace WpPack\Component\Scim\Schema;

final readonly class ServiceProviderConfig
{
    public function __construct(
        public int $maxResults = 100,
        public bool $changePasswordSupported = false,
        public bool $patchSupported = true,
        public bool $bulkSupported = false,
        public bool $filterSupported = true,
        public int $filterMaxResults = 200,
        public bool $sortSupported = false,
        public bool $etagSupported = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'schemas' => [ScimConstants::SERVICE_PROVIDER_CONFIG_SCHEMA],
            'documentationUri' => 'https://tools.ietf.org/html/rfc7644',
            'patch' => [
                'supported' => $this->patchSupported,
            ],
            'bulk' => [
                'supported' => $this->bulkSupported,
                'maxOperations' => 0,
                'maxPayloadSize' => 0,
            ],
            'filter' => [
                'supported' => $this->filterSupported,
                'maxResults' => $this->filterMaxResults,
            ],
            'changePassword' => [
                'supported' => $this->changePasswordSupported,
            ],
            'sort' => [
                'supported' => $this->sortSupported,
            ],
            'etag' => [
                'supported' => $this->etagSupported,
            ],
            'authenticationSchemes' => [
                [
                    'type' => 'oauthbearertoken',
                    'name' => 'OAuth Bearer Token',
                    'description' => 'Authentication scheme using the OAuth Bearer Token Standard',
                    'specUri' => 'https://tools.ietf.org/html/rfc6750',
                ],
            ],
            'meta' => [
                'resourceType' => 'ServiceProviderConfig',
                'location' => '/scim/v2/ServiceProviderConfig',
            ],
        ];
    }
}
