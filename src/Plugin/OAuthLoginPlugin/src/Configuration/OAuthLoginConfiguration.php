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

final readonly class OAuthLoginConfiguration
{
    /**
     * @param array<string, ProviderConfiguration> $providers
     */
    public function __construct(
        public array $providers,
        public bool $ssoOnly = false,
        public bool $autoProvision = false,
        public string $defaultRole = 'subscriber',
        public string $callbackPath = '/oauth/callback',
        public string $authorizePath = '/oauth/authorize',
        public string $verifyPath = '/oauth/verify',
    ) {}

    public static function fromEnvironment(): self
    {
        if (!\defined('OAUTH_PROVIDERS') || !\is_array(\constant('OAUTH_PROVIDERS'))) {
            throw new \RuntimeException('OAUTH_PROVIDERS is not configured.');
        }

        $globalAutoProvision = \defined('OAUTH_AUTO_PROVISION') && \constant('OAUTH_AUTO_PROVISION');
        $globalDefaultRole = \defined('OAUTH_DEFAULT_ROLE') ? (string) \constant('OAUTH_DEFAULT_ROLE') : 'subscriber';

        $providers = [];

        /** @var array<string, array<string, mixed>> $providerConfigs */
        $providerConfigs = \constant('OAUTH_PROVIDERS');

        foreach ($providerConfigs as $name => $p) {
            $providers[$name] = new ProviderConfiguration(
                name: $name,
                type: (string) $p['type'],
                clientId: (string) $p['client_id'],
                clientSecret: (string) $p['client_secret'],
                label: (string) ($p['label'] ?? $name),
                tenantId: isset($p['tenant_id']) ? (string) $p['tenant_id'] : null,
                hostedDomain: isset($p['hosted_domain']) ? (string) $p['hosted_domain'] : null,
                discoveryUrl: isset($p['discovery_url']) ? (string) $p['discovery_url'] : null,
                scopes: isset($p['scopes']) && \is_array($p['scopes']) ? $p['scopes'] : null,
                autoProvision: (bool) ($p['auto_provision'] ?? $globalAutoProvision),
                defaultRole: (string) ($p['default_role'] ?? $globalDefaultRole),
                roleClaim: isset($p['role_claim']) ? (string) $p['role_claim'] : null,
                roleMapping: isset($p['role_mapping']) && \is_array($p['role_mapping']) ? $p['role_mapping'] : null,
            );
        }

        $ssoOnly = \defined('OAUTH_SSO_ONLY') && \constant('OAUTH_SSO_ONLY');
        $callbackPath = \defined('OAUTH_CALLBACK_PATH') ? (string) \constant('OAUTH_CALLBACK_PATH') : '/oauth/callback';
        $authorizePath = \defined('OAUTH_AUTHORIZE_PATH') ? (string) \constant('OAUTH_AUTHORIZE_PATH') : '/oauth/authorize';
        $verifyPath = \defined('OAUTH_VERIFY_PATH') ? (string) \constant('OAUTH_VERIFY_PATH') : '/oauth/verify';

        return new self(
            providers: $providers,
            ssoOnly: $ssoOnly,
            autoProvision: $globalAutoProvision,
            defaultRole: $globalDefaultRole,
            callbackPath: $callbackPath,
            authorizePath: $authorizePath,
            verifyPath: $verifyPath,
        );
    }

    public function getProvider(string $name): ?ProviderConfiguration
    {
        return $this->providers[$name] ?? null;
    }
}
