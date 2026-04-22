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

namespace WPPack\Plugin\OAuthLoginPlugin\Configuration;

final readonly class OAuthLoginConfiguration
{
    /**
     * @param array<string, ProviderConfiguration> $providers
     */
    public function __construct(
        public array $providers,
        public bool $ssoOnly = false,
        public bool $autoProvision = false,
        public string $authorizePath = '/oauth/{provider}/authorize',
        public string $callbackPath = '/oauth/{provider}/callback',
        public string $verifyPath = '/oauth/{provider}/verify',
        public string $buttonDisplay = 'icon-text',
    ) {}

    public function getAuthorizePath(string $provider): string
    {
        return str_replace('{provider}', $provider, $this->authorizePath);
    }

    public function getCallbackPath(string $provider): string
    {
        return str_replace('{provider}', $provider, $this->callbackPath);
    }

    public function getVerifyPath(string $provider): string
    {
        return str_replace('{provider}', $provider, $this->verifyPath);
    }

    public const OPTION_NAME = 'wppack_oauth_login';

    public const MASKED_VALUE = '********';

    /**
     * Create from constants with wp_options fallback.
     */
    public static function fromEnvironmentOrOptions(): self
    {
        $raw = get_option(self::OPTION_NAME, []);
        $savedConfig = \is_array($raw) ? $raw : [];

        // Providers: constant takes priority, then wp_options
        $constProviders = \defined('OAUTH_PROVIDERS') && \is_array(\constant('OAUTH_PROVIDERS'))
            ? \constant('OAUTH_PROVIDERS')
            : [];
        /** @var array<string, array<string, mixed>> $savedProviders */
        $savedProviders = $savedConfig['providers'] ?? [];

        $globalAutoProvision = \defined('OAUTH_AUTO_PROVISION')
            ? (bool) \constant('OAUTH_AUTO_PROVISION')
            : (bool) ($savedConfig['autoProvision'] ?? false);
        $ssoOnly = \defined('OAUTH_SSO_ONLY')
            ? (bool) \constant('OAUTH_SSO_ONLY')
            : (bool) ($savedConfig['ssoOnly'] ?? false);
        $authorizePath = \defined('OAUTH_AUTHORIZE_PATH')
            ? (string) \constant('OAUTH_AUTHORIZE_PATH')
            : (string) ($savedConfig['authorizePath'] ?? '/oauth/{provider}/authorize');
        $callbackPath = \defined('OAUTH_CALLBACK_PATH')
            ? (string) \constant('OAUTH_CALLBACK_PATH')
            : (string) ($savedConfig['callbackPath'] ?? '/oauth/{provider}/callback');
        $verifyPath = \defined('OAUTH_VERIFY_PATH')
            ? (string) \constant('OAUTH_VERIFY_PATH')
            : (string) ($savedConfig['verifyPath'] ?? '/oauth/{provider}/verify');
        $buttonDisplay = \defined('OAUTH_BUTTON_DISPLAY')
            ? (string) \constant('OAUTH_BUTTON_DISPLAY')
            : (string) ($savedConfig['buttonDisplay'] ?? 'icon-text');

        // Merge: constant providers override saved ones by name
        $mergedProviders = $savedProviders;
        foreach ($constProviders as $name => $p) {
            $mergedProviders[$name] = $p;
        }

        $providers = [];
        foreach ($mergedProviders as $name => $p) {
            $name = (string) $name;
            if (!preg_match('/^[a-z0-9\-]+$/', $name)) {
                continue;
            }
            if (!isset($p['type'], $p['client_id'], $p['client_secret'])) {
                continue;
            }

            $providers[$name] = new ProviderConfiguration(
                name: $name,
                type: (string) $p['type'],
                clientId: (string) $p['client_id'],
                clientSecret: (string) $p['client_secret'],
                label: (string) ($p['label'] ?? $name),
                tenantId: isset($p['tenant_id']) ? (string) $p['tenant_id'] : null,
                domain: isset($p['domain']) ? (string) $p['domain'] : null,
                hostedDomain: isset($p['hosted_domain']) ? (string) $p['hosted_domain'] : null,
                discoveryUrl: isset($p['discovery_url']) ? (string) $p['discovery_url'] : null,
                scopes: isset($p['scopes']) && \is_array($p['scopes']) ? array_values(array_map('strval', $p['scopes'])) : null,
                autoProvision: (bool) ($p['auto_provision'] ?? $globalAutoProvision),
                buttonStyle: isset($p['button_style']) ? (string) $p['button_style'] : null,
            );
        }

        return new self(
            providers: $providers,
            ssoOnly: $ssoOnly,
            autoProvision: $globalAutoProvision,
            authorizePath: $authorizePath,
            callbackPath: $callbackPath,
            verifyPath: $verifyPath,
            buttonDisplay: $buttonDisplay,
        );
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
                domain: isset($p['domain']) ? (string) $p['domain'] : null,
                hostedDomain: isset($p['hosted_domain']) ? (string) $p['hosted_domain'] : null,
                discoveryUrl: isset($p['discovery_url']) ? (string) $p['discovery_url'] : null,
                scopes: isset($p['scopes']) && \is_array($p['scopes']) ? array_values(array_map('strval', $p['scopes'])) : null,
                autoProvision: (bool) ($p['auto_provision'] ?? $globalAutoProvision),
                buttonStyle: isset($p['button_style']) ? (string) $p['button_style'] : null,
            );
        }

        $ssoOnly = \defined('OAUTH_SSO_ONLY') && \constant('OAUTH_SSO_ONLY');
        $authorizePath = \defined('OAUTH_AUTHORIZE_PATH') ? (string) \constant('OAUTH_AUTHORIZE_PATH') : '/oauth/{provider}/authorize';
        $callbackPath = \defined('OAUTH_CALLBACK_PATH') ? (string) \constant('OAUTH_CALLBACK_PATH') : '/oauth/{provider}/callback';
        $verifyPath = \defined('OAUTH_VERIFY_PATH') ? (string) \constant('OAUTH_VERIFY_PATH') : '/oauth/{provider}/verify';

        return new self(
            providers: $providers,
            ssoOnly: $ssoOnly,
            autoProvision: $globalAutoProvision,
            authorizePath: $authorizePath,
            callbackPath: $callbackPath,
            verifyPath: $verifyPath,
        );
    }

    public function getProvider(string $name): ?ProviderConfiguration
    {
        return $this->providers[$name] ?? null;
    }
}
