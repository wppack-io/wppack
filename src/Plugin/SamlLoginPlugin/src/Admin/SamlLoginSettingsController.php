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

namespace WpPack\Plugin\SamlLoginPlugin\Admin;

use WpPack\Component\HttpFoundation\JsonResponse;
use WpPack\Component\Rest\AbstractRestController;
use WpPack\Component\Rest\Attribute\RestRoute;
use WpPack\Component\Rest\HttpMethod;
use WpPack\Component\Role\Attribute\IsGranted;
use WpPack\Component\Role\RoleProvider;
use WpPack\Component\Sanitizer\Sanitizer;
use WpPack\Plugin\SamlLoginPlugin\Configuration\SamlLoginConfiguration;

#[RestRoute(namespace: 'wppack/v1/saml-login')]
#[IsGranted('manage_options')]
final class SamlLoginSettingsController extends AbstractRestController
{
    private const OPTION_NAME = 'wppack_saml_login';

    private const SENSITIVE_FIELDS = ['idpX509Cert', 'idpCertFingerprint'];

    private const MASKED_VALUE = '********';

    private const URL_FIELDS = ['idpSsoUrl', 'idpSloUrl', 'spEntityId', 'spAcsUrl', 'spSloUrl'];

    private const PATH_FIELDS = ['metadataPath', 'acsPath', 'sloPath'];


    public function __construct(
        private readonly SamlLoginConfiguration $configuration,
        private readonly Sanitizer $sanitizer,
        private readonly RoleProvider $roleProvider,
    ) {}

    #[RestRoute(route: '/settings', methods: HttpMethod::GET)]
    public function getSettings(): JsonResponse
    {
        return $this->json($this->buildDisplayArray());
    }

    #[RestRoute(route: '/settings', methods: HttpMethod::POST)]
    public function saveSettings(\WP_REST_Request $request): JsonResponse
    {
        /** @var array<string, mixed> $params */
        $params = $request->get_json_params();

        $this->persistOptions($params);

        $updated = SamlLoginConfiguration::fromEnvironmentOrOptions();

        return $this->json($this->buildDisplayArrayFrom($updated));
    }

    /**
     * @return array<string, array{value: mixed, source: string, readonly: bool}>
     */
    private function buildDisplayArray(): array
    {
        return $this->buildDisplayArrayFrom($this->configuration);
    }

    /**
     * @return array<string, array{value: mixed, source: string, readonly: bool}>
     */
    private function buildDisplayArrayFrom(SamlLoginConfiguration $config): array
    {
        $raw = get_option(self::OPTION_NAME, []);
        $options = \is_array($raw) ? $raw : [];

        $result = [];
        $reflection = new \ReflectionClass($config);

        foreach ($reflection->getConstructor()?->getParameters() ?? [] as $param) {
            $name = $param->getName();
            $envName = SamlLoginConfiguration::ENV_MAP[$name] ?? null;

            $source = 'default';
            if ($envName !== null && \defined($envName)) {
                $source = 'constant';
            } elseif (isset($options[$name])) {
                $source = 'option';
            } elseif ($envName !== null && $this->getEnvValue($envName) !== null) {
                $source = 'env';
            }

            $value = $config->{$name};
            if (\in_array($name, self::SENSITIVE_FIELDS, true) && $value !== null && $value !== '') {
                $value = self::MASKED_VALUE;
            }
            if ($name === 'roleMapping' && \is_array($value)) {
                $value = json_encode($value, \JSON_UNESCAPED_UNICODE);
            }

            $result[$name] = [
                'value' => $value,
                'source' => $source,
                'readonly' => $source === 'constant',
            ];
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $input
     */
    private function persistOptions(array $input): void
    {
        $raw = get_option(self::OPTION_NAME, []);
        $options = \is_array($raw) ? $raw : [];

        $reflection = new \ReflectionClass(SamlLoginConfiguration::class);

        foreach ($reflection->getConstructor()?->getParameters() ?? [] as $param) {
            $name = $param->getName();
            $envName = SamlLoginConfiguration::ENV_MAP[$name] ?? null;

            if ($envName !== null && \defined($envName)) {
                continue;
            }

            if (!\array_key_exists($name, $input)) {
                continue;
            }

            if (\in_array($name, self::SENSITIVE_FIELDS, true) && $input[$name] === self::MASKED_VALUE) {
                continue;
            }

            $value = $input[$name];

            if (\in_array($name, self::URL_FIELDS, true) && \is_string($value) && $value !== '') {
                $value = $this->sanitizer->url($value);
                if ($value === '') {
                    continue;
                }
            }

            if (\in_array($name, self::PATH_FIELDS, true) && \is_string($value)) {
                if ($value !== '' && !str_starts_with($value, '/')) {
                    continue;
                }
            }

            if ($name === 'defaultRole' && \is_string($value) && $value !== '') {
                $roles = $this->roleProvider->getNames();
                if (!isset($roles[$value])) {
                    continue;
                }
            }

            $options[$name] = $value;
        }

        update_option(self::OPTION_NAME, $options);
    }

    private function getEnvValue(string $name): ?string
    {
        if (\defined($name)) {
            $value = \constant($name);

            return \is_string($value) && $value !== '' ? $value : null;
        }

        $value = $_ENV[$name] ?? false;
        if ($value !== false && $value !== '') {
            return $value;
        }

        $value = getenv($name);

        return ($value !== false && $value !== '') ? $value : null;
    }
}
