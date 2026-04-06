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

namespace WpPack\Plugin\PasskeyLoginPlugin\Admin;

use WpPack\Component\HttpFoundation\JsonResponse;
use WpPack\Component\Rest\AbstractRestController;
use WpPack\Component\Rest\Attribute\RestRoute;
use WpPack\Component\Rest\HttpMethod;
use WpPack\Component\Role\Attribute\IsGranted;
use WpPack\Plugin\PasskeyLoginPlugin\Configuration\PasskeyLoginConfiguration;

#[RestRoute(namespace: 'wppack/v1/passkey-login')]
#[IsGranted('manage_options')]
final class PasskeyLoginSettingsController extends AbstractRestController
{
    #[RestRoute(route: '/settings', methods: HttpMethod::GET)]
    public function getSettings(): JsonResponse
    {
        $blogId = is_multisite() ? get_main_site_id() : null;

        return $this->json([
            'siteUrl' => get_home_url($blogId),
            ...$this->buildResponse(),
        ]);
    }

    #[RestRoute(route: '/settings', methods: HttpMethod::POST)]
    public function saveSettings(\WP_REST_Request $request): JsonResponse
    {
        /** @var array<string, mixed> $params */
        $params = $request->get_json_params();

        $this->persistOptions($params);

        $blogId = is_multisite() ? get_main_site_id() : null;

        return $this->json([
            'siteUrl' => get_home_url($blogId),
            ...$this->buildResponse(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildResponse(): array
    {
        $raw = get_option(PasskeyLoginConfiguration::OPTION_NAME, []);
        $saved = \is_array($raw) ? $raw : [];

        $fields = [
            'enabled' => ['const' => 'PASSKEY_ENABLED', 'default' => true],
            'rpName' => ['const' => 'PASSKEY_RP_NAME', 'default' => ''],
            'rpId' => ['const' => 'PASSKEY_RP_ID', 'default' => ''],
            'allowSignup' => ['const' => 'PASSKEY_ALLOW_SIGNUP', 'default' => false],
            'requireUserVerification' => ['const' => 'PASSKEY_REQUIRE_USER_VERIFICATION', 'default' => 'preferred'],
        ];

        $settings = [];
        foreach ($fields as $key => $meta) {
            $source = 'default';
            $value = $meta['default'];

            if (\defined($meta['const'])) {
                $source = 'constant';
                $value = \constant($meta['const']);
            } elseif (getenv($meta['const']) !== false) {
                $source = 'constant';
                $value = getenv($meta['const']);
            } elseif (\array_key_exists($key, $saved)) {
                $source = 'option';
                $value = $saved[$key];
            }

            // Cast booleans
            if (\is_bool($meta['default'])) {
                $value = filter_var($value, \FILTER_VALIDATE_BOOLEAN);
            }

            $settings[$key] = [
                'value' => $value,
                'source' => $source,
                'readonly' => $source === 'constant',
            ];
        }

        return [
            'settings' => $settings,
        ];
    }

    /**
     * @param array<string, mixed> $input
     */
    private function persistOptions(array $input): void
    {
        $raw = get_option(PasskeyLoginConfiguration::OPTION_NAME, []);
        $saved = \is_array($raw) ? $raw : [];

        $fieldMap = [
            'enabled' => 'PASSKEY_ENABLED',
            'rpName' => 'PASSKEY_RP_NAME',
            'rpId' => 'PASSKEY_RP_ID',
            'allowSignup' => 'PASSKEY_ALLOW_SIGNUP',
            'requireUserVerification' => 'PASSKEY_REQUIRE_USER_VERIFICATION',
        ];

        $allowedUserVerification = ['preferred', 'required', 'discouraged'];

        foreach ($fieldMap as $key => $constName) {
            if (\defined($constName) || !\array_key_exists($key, $input)) {
                continue;
            }

            // Validate requireUserVerification
            if ($key === 'requireUserVerification' && !\in_array($input[$key], $allowedUserVerification, true)) {
                continue;
            }

            $saved[$key] = $input[$key];
        }

        update_option(PasskeyLoginConfiguration::OPTION_NAME, $saved);
    }
}
