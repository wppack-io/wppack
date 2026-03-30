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

namespace WpPack\Component\Security\Bridge\OAuth\Provider;

/**
 * Self-describing metadata for an OAuth provider type.
 *
 * @param list<string> $requiredFields Additional required config fields (e.g. ['domain'], ['tenant_id'])
 * @param list<string> $defaultScopes Default OAuth scopes
 */
final readonly class ProviderDefinition
{
    /**
     * @param list<string> $requiredFields
     * @param list<string> $defaultScopes
     */
    public function __construct(
        public string $type,
        public string $label,
        public string $dropdownLabel,
        public bool $oidc,
        public array $requiredFields = [],
        public array $defaultScopes = ['openid', 'email', 'profile'],
    ) {}
}
