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

namespace WpPack\Plugin\OAuthLoginPlugin\Configuration;

/**
 * Value object holding one provider's settings.
 */
final readonly class ProviderConfiguration
{
    /**
     * @param list<string>|null $scopes
     * @param array<string, string>|null $roleMapping
     */
    public function __construct(
        public string $name,
        public string $type,
        public string $clientId,
        #[\SensitiveParameter]
        public string $clientSecret,
        public string $label,
        public ?string $tenantId = null,
        public ?string $domain = null,
        public ?string $hostedDomain = null,
        public ?string $discoveryUrl = null,
        public ?array $scopes = null,
        public bool $autoProvision = false,
        public string $defaultRole = 'subscriber',
        public ?string $roleClaim = null,
        public ?array $roleMapping = null,
    ) {}
}
