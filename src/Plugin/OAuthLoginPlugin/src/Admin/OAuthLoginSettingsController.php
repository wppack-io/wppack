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

namespace WPPack\Plugin\OAuthLoginPlugin\Admin;

use WPPack\Component\HttpFoundation\JsonResponse;
use WPPack\Component\Option\OptionManager;
use WPPack\Component\Rest\AbstractRestController;
use WPPack\Component\Rest\Attribute\RestRoute;
use WPPack\Component\Rest\HttpMethod;
use WPPack\Component\Role\Attribute\IsGranted;
use WPPack\Component\Sanitizer\Sanitizer;
use WPPack\Component\Security\Bridge\OAuth\Assets\ProviderIcons;
use WPPack\Component\Security\Bridge\OAuth\Provider\ProviderRegistry;
use WPPack\Component\Site\BlogContext;
use WPPack\Component\Site\BlogContextInterface;
use WPPack\Plugin\OAuthLoginPlugin\Configuration\OAuthLoginConfiguration;
use WPPack\Plugin\OAuthLoginPlugin\Configuration\ProviderConfiguration;

#[RestRoute(namespace: 'wppack/v1/oauth-login')]
#[IsGranted('manage_options')]
final class OAuthLoginSettingsController extends AbstractRestController
{
    private const PROVIDER_SENSITIVE_FIELDS = ['client_secret'];

    private const PROVIDER_URL_FIELDS = ['discovery_url'];

    public function __construct(
        private readonly OAuthLoginConfiguration $configuration,
        private readonly Sanitizer $sanitizer,
        private readonly BlogContextInterface $blogContext = new BlogContext(),
        private readonly OptionManager $optionManager = new OptionManager(),
    ) {}

    #[RestRoute(route: '/settings', methods: HttpMethod::GET)]
    public function getSettings(): JsonResponse
    {
        return $this->json($this->buildResponse($this->resolveMainBlogId()));
    }

    #[RestRoute(route: '/settings', methods: HttpMethod::POST)]
    public function saveSettings(\WP_REST_Request $request): JsonResponse
    {
        /** @var array<string, mixed> $params */
        $params = $request->get_json_params();

        $this->persistOptions($params);

        // Flush rewrite rules so route changes take effect
        $this->optionManager->delete('rewrite_rules');

        $updated = OAuthLoginConfiguration::fromEnvironmentOrOptions();

        // Rebuild display from updated config
        $ctrl = new self($updated, $this->sanitizer, $this->blogContext, $this->optionManager);

        return $this->json($ctrl->buildResponse($this->resolveMainBlogId()));
    }

    private function resolveMainBlogId(): ?int
    {
        return $this->blogContext->isMultisite() ? $this->blogContext->getMainSiteId() : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildResponse(?int $blogId): array
    {
        $icons = [];
        $styles = [];
        foreach (ProviderIcons::providers() as $type) {
            $icons[$type] = ProviderIcons::svg($type);
            $styles[$type] = ProviderIcons::styles($type);
        }

        $definitions = [];
        foreach (ProviderRegistry::definitions() as $type => $def) {
            $definitions[$type] = [
                'type' => $def->type,
                'label' => $def->label,
                'dropdownLabel' => $def->dropdownLabel,
                'oidc' => $def->oidc,
                'requiredFields' => $def->requiredFields,
                'optionalFields' => $def->optionalFields,
                'defaultScopes' => $def->defaultScopes,
            ];
        }

        return [
            'siteUrl' => get_home_url($blogId),
            'icons' => $icons,
            'styles' => $styles,
            'definitions' => $definitions,
            'global' => $this->buildGlobalDisplay(),
            'providers' => $this->buildProvidersDisplay(),
        ];
    }

    /**
     * @return array<string, array{value: mixed, source: string, readonly: bool}>
     */
    private function buildGlobalDisplay(): array
    {
        $raw = $this->optionManager->get(OAuthLoginConfiguration::OPTION_NAME, []);
        $saved = \is_array($raw) ? $raw : [];

        $fields = [
            'ssoOnly' => ['const' => 'OAUTH_SSO_ONLY', 'value' => $this->configuration->ssoOnly],
            'autoProvision' => ['const' => 'OAUTH_AUTO_PROVISION', 'value' => $this->configuration->autoProvision],
            'authorizePath' => ['const' => 'OAUTH_AUTHORIZE_PATH', 'value' => $this->configuration->authorizePath],
            'callbackPath' => ['const' => 'OAUTH_CALLBACK_PATH', 'value' => $this->configuration->callbackPath],
            'verifyPath' => ['const' => 'OAUTH_VERIFY_PATH', 'value' => $this->configuration->verifyPath],
            'buttonDisplay' => ['const' => 'OAUTH_BUTTON_DISPLAY', 'value' => $this->configuration->buttonDisplay],
        ];

        $result = [];
        foreach ($fields as $key => $meta) {
            $source = 'default';
            if (\defined($meta['const'])) {
                $source = 'constant';
            } elseif (isset($saved[$key])) {
                $source = 'option';
            }

            $result[$key] = [
                'value' => $meta['value'],
                'source' => $source,
                'readonly' => $source === 'constant',
            ];
        }

        return $result;
    }

    /**
     * @return array<string, array{source: string, readonly: bool, fields: array<string, mixed>}>
     */
    private function buildProvidersDisplay(): array
    {
        $constProviders = \defined('OAUTH_PROVIDERS') && \is_array(\constant('OAUTH_PROVIDERS'))
            ? array_keys(\constant('OAUTH_PROVIDERS'))
            : [];

        $result = [];
        foreach ($this->configuration->providers as $name => $provider) {
            $isConst = \in_array($name, $constProviders, true);

            $result[$name] = [
                'source' => $isConst ? 'constant' : 'option',
                'readonly' => $isConst,
                'icon' => ProviderIcons::svg($provider->type) ?? ProviderIcons::svg($name),
                'fields' => $this->buildProviderFields($provider),
            ];
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildProviderFields(ProviderConfiguration $provider): array
    {
        $fields = [
            'type' => $provider->type,
            'client_id' => $provider->clientId,
            'client_secret' => OAuthLoginConfiguration::MASKED_VALUE,
            'label' => $provider->label,
            'tenant_id' => $provider->tenantId,
            'domain' => $provider->domain,
            'hosted_domain' => $provider->hostedDomain,
            'discovery_url' => $provider->discoveryUrl,
            'scopes' => $provider->scopes !== null ? implode(' ', $provider->scopes) : '',
            'auto_provision' => $provider->autoProvision,
            'button_style' => $provider->buttonStyle,
        ];

        return $fields;
    }

    /**
     * @param array<string, mixed> $input
     */
    private function persistOptions(array $input): void
    {
        $raw = $this->optionManager->get(OAuthLoginConfiguration::OPTION_NAME, []);
        $saved = \is_array($raw) ? $raw : [];

        // Global settings
        if (isset($input['global']) && \is_array($input['global'])) {
            /** @var array<string, mixed> $globalInput */
            $globalInput = $input['global'];

            $globalFields = [
                'ssoOnly' => 'OAUTH_SSO_ONLY',
                'autoProvision' => 'OAUTH_AUTO_PROVISION',
                'authorizePath' => 'OAUTH_AUTHORIZE_PATH',
                'callbackPath' => 'OAUTH_CALLBACK_PATH',
                'verifyPath' => 'OAUTH_VERIFY_PATH',
                'buttonDisplay' => 'OAUTH_BUTTON_DISPLAY',
            ];
            foreach ($globalFields as $key => $constName) {
                if (\defined($constName) || !\array_key_exists($key, $globalInput)) {
                    continue;
                }

                if (\in_array($key, ['authorizePath', 'callbackPath', 'verifyPath'], true) && \is_string($globalInput[$key])) {
                    if ($globalInput[$key] !== '' && !str_starts_with($globalInput[$key], '/')) {
                        continue;
                    }
                }

                $saved[$key] = $globalInput[$key];
            }
        }

        // Providers
        if (isset($input['providers']) && \is_array($input['providers'])) {
            $constProviderNames = \defined('OAUTH_PROVIDERS') && \is_array(\constant('OAUTH_PROVIDERS'))
                ? array_keys(\constant('OAUTH_PROVIDERS'))
                : [];

            /** @var array<string, array<string, mixed>> $savedProviders */
            $savedProviders = $saved['providers'] ?? [];

            foreach ($input['providers'] as $name => $providerInput) {
                if (!\is_string($name) || !\is_array($providerInput)) {
                    continue;
                }

                if (\in_array($name, $constProviderNames, true)) {
                    continue;
                }

                // Validate URL fields
                foreach (self::PROVIDER_URL_FIELDS as $urlField) {
                    if (isset($providerInput[$urlField]) && \is_string($providerInput[$urlField]) && $providerInput[$urlField] !== '') {
                        $providerInput[$urlField] = $this->sanitizer->url($providerInput[$urlField]);
                    }
                }

                // Skip masked sensitive fields
                foreach (self::PROVIDER_SENSITIVE_FIELDS as $sensitiveField) {
                    if (isset($providerInput[$sensitiveField]) && $providerInput[$sensitiveField] === OAuthLoginConfiguration::MASKED_VALUE) {
                        if (isset($savedProviders[$name][$sensitiveField])) {
                            $providerInput[$sensitiveField] = $savedProviders[$name][$sensitiveField];
                        } else {
                            unset($providerInput[$sensitiveField]);
                        }
                    }
                }

                $savedProviders[$name] = $providerInput;
            }

            $saved['providers'] = $savedProviders;
        }

        // Reorder providers
        if (isset($input['providerOrder']) && \is_array($input['providerOrder'])) {
            /** @var array<string, array<string, mixed>> $currentProviders */
            $currentProviders = $saved['providers'] ?? [];
            $ordered = [];
            foreach ($input['providerOrder'] as $name) {
                if (\is_string($name) && isset($currentProviders[$name])) {
                    $ordered[$name] = $currentProviders[$name];
                }
            }
            // Append any providers not in order list (e.g. constant-defined)
            foreach ($currentProviders as $name => $data) {
                if (!isset($ordered[$name])) {
                    $ordered[$name] = $data;
                }
            }
            $saved['providers'] = $ordered;
        }

        // Delete providers (only wp_options-sourced ones)
        if (isset($input['deletedProviders']) && \is_array($input['deletedProviders'])) {
            $constProviderNames ??= \defined('OAUTH_PROVIDERS') && \is_array(\constant('OAUTH_PROVIDERS'))
                ? array_keys(\constant('OAUTH_PROVIDERS'))
                : [];

            /** @var array<string, array<string, mixed>> $providers */
            $providers = $saved['providers'] ?? [];

            foreach ($input['deletedProviders'] as $name) {
                if (\is_string($name) && !\in_array($name, $constProviderNames, true)) {
                    unset($providers[$name]);
                }
            }

            $saved['providers'] = $providers;
        }

        $this->optionManager->update(OAuthLoginConfiguration::OPTION_NAME, $saved);

    }
}
