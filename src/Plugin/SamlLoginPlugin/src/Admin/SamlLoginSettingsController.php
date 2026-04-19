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

namespace WPPack\Plugin\SamlLoginPlugin\Admin;

use WPPack\Component\HttpFoundation\JsonResponse;
use WPPack\Component\HttpFoundation\Response;
use WPPack\Component\Rest\AbstractRestController;
use WPPack\Component\Rest\Attribute\RestRoute;
use WPPack\Component\Rest\HttpMethod;
use WPPack\Component\Role\Attribute\IsGranted;
use WPPack\Component\Sanitizer\Sanitizer;
use WPPack\Component\Security\Bridge\SAML\Configuration\SpMetadataExporter;
use WPPack\Plugin\SamlLoginPlugin\Configuration\SamlLoginConfiguration;

#[RestRoute(namespace: 'wppack/v1/saml-login')]
#[IsGranted('manage_options')]
final class SamlLoginSettingsController extends AbstractRestController
{
    private const OPTION_NAME = 'wppack_saml_login';

    private const SENSITIVE_FIELDS = ['idpX509Cert', 'idpCertFingerprint'];

    private const MASKED_VALUE = '********';

    private const URL_FIELDS = ['idpSsoUrl', 'idpSloUrl', 'spEntityId'];

    private const PATH_FIELDS = ['metadataPath', 'acsPath', 'sloPath'];


    public function __construct(
        private readonly SamlLoginConfiguration $configuration,
        private readonly Sanitizer $sanitizer,
        private readonly ?SpMetadataExporter $metadataExporter = null,
    ) {}

    #[RestRoute(route: '/settings', methods: HttpMethod::GET)]
    public function getSettings(): JsonResponse
    {
        $blogId = is_multisite() ? get_main_site_id() : null;

        return $this->json([
            'siteUrl' => get_home_url($blogId),
            'fields' => $this->buildDisplayArray(),
        ]);
    }

    #[RestRoute(route: '/metadata', methods: HttpMethod::GET)]
    public function downloadMetadata(): Response
    {
        if ($this->metadataExporter === null) {
            return $this->response('SP metadata is not available. SAML is not fully configured.', 400);
        }

        $xml = $this->metadataExporter->toXml();

        return $this->response($xml, 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => 'attachment; filename="sp-metadata.xml"',
        ]);
    }

    #[RestRoute(route: '/settings', methods: HttpMethod::POST)]
    public function saveSettings(\WP_REST_Request $request): JsonResponse
    {
        /** @var array<string, mixed> $params */
        $params = $request->get_json_params();

        $this->persistOptions($params);

        delete_option('rewrite_rules');

        $updated = SamlLoginConfiguration::fromEnvironmentOrOptions();
        $blogId = is_multisite() ? get_main_site_id() : null;

        return $this->json([
            'siteUrl' => get_home_url($blogId),
            'fields' => $this->buildDisplayArrayFrom($updated),
        ]);
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
