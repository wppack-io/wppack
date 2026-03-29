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
        public string $basePath = '/oauth',
    ) {}

    public function getAuthorizePath(string $provider): string
    {
        return $this->basePath . '/' . $provider . '/authorize';
    }

    public function getCallbackPath(string $provider): string
    {
        return $this->basePath . '/' . $provider . '/callback';
    }

    public function getVerifyPath(string $provider): string
    {
        return $this->basePath . '/' . $provider . '/verify';
    }

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
            if (!preg_match('/^[a-z0-9\-]+$/', $name)) {
                throw new \RuntimeException(\sprintf('OAuth provider name "%s" is invalid. Use only lowercase letters, numbers, and hyphens.', $name));
            }

            if (!isset($p['type'], $p['client_id'], $p['client_secret'])) {
                throw new \RuntimeException(\sprintf('OAuth provider "%s" is missing required fields: type, client_id, client_secret.', $name));
            }

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
        $basePath = \defined('OAUTH_BASE_PATH') ? (string) \constant('OAUTH_BASE_PATH') : '/oauth';

        return new self(
            providers: $providers,
            ssoOnly: $ssoOnly,
            autoProvision: $globalAutoProvision,
            defaultRole: $globalDefaultRole,
            basePath: $basePath,
        );
    }

    public function getProvider(string $name): ?ProviderConfiguration
    {
        return $this->providers[$name] ?? null;
    }
}
