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

namespace WPPack\Plugin\PasskeyLoginPlugin\Admin;

use WPPack\Component\HttpFoundation\JsonResponse;
use WPPack\Component\Rest\AbstractRestController;
use WPPack\Component\Rest\Attribute\RestRoute;
use WPPack\Component\Rest\HttpMethod;
use WPPack\Component\Role\Attribute\IsGranted;
use WPPack\Component\Site\BlogContext;
use WPPack\Component\Site\BlogContextInterface;
use WPPack\Plugin\PasskeyLoginPlugin\Configuration\PasskeyLoginConfiguration;

#[RestRoute(namespace: 'wppack/v1/passkey-login')]
#[IsGranted('manage_options')]
final class PasskeyLoginSettingsController extends AbstractRestController
{
    public function __construct(
        private readonly BlogContextInterface $blogContext = new BlogContext(),
    ) {}

    #[RestRoute(route: '/settings', methods: HttpMethod::GET)]
    public function getSettings(): JsonResponse
    {
        return $this->json([
            'siteUrl' => get_home_url($this->resolveMainBlogId()),
            ...$this->buildResponse(),
        ]);
    }

    #[RestRoute(route: '/settings', methods: HttpMethod::POST)]
    public function saveSettings(\WP_REST_Request $request): JsonResponse
    {
        /** @var array<string, mixed> $params */
        $params = $request->get_json_params();

        $this->persistOptions($params);

        return $this->json([
            'siteUrl' => get_home_url($this->resolveMainBlogId()),
            ...$this->buildResponse(),
        ]);
    }

    private function resolveMainBlogId(): ?int
    {
        return $this->blogContext->isMultisite() ? $this->blogContext->getMainSiteId() : null;
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
            'algorithms' => ['const' => 'PASSKEY_ALGORITHMS', 'default' => [-7, -257]],
            'attestation' => ['const' => 'PASSKEY_ATTESTATION', 'default' => 'none'],
            'authenticatorAttachment' => ['const' => 'PASSKEY_AUTHENTICATOR_ATTACHMENT', 'default' => ''],
            'timeout' => ['const' => 'PASSKEY_TIMEOUT', 'default' => 60000],
            'residentKey' => ['const' => 'PASSKEY_RESIDENT_KEY', 'default' => 'required'],
            'buttonDisplay' => ['const' => 'PASSKEY_BUTTON_DISPLAY', 'default' => 'icon-text'],
            'maxCredentialsPerUser' => ['const' => 'PASSKEY_MAX_CREDENTIALS_PER_USER', 'default' => 3],
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

            // Cast int
            if (\is_int($meta['default'])) {
                $value = (int) $value;
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
            'algorithms' => 'PASSKEY_ALGORITHMS',
            'attestation' => 'PASSKEY_ATTESTATION',
            'authenticatorAttachment' => 'PASSKEY_AUTHENTICATOR_ATTACHMENT',
            'timeout' => 'PASSKEY_TIMEOUT',
            'residentKey' => 'PASSKEY_RESIDENT_KEY',
            'buttonDisplay' => 'PASSKEY_BUTTON_DISPLAY',
            'maxCredentialsPerUser' => 'PASSKEY_MAX_CREDENTIALS_PER_USER',
        ];

        $allowedUserVerification = ['preferred', 'required', 'discouraged'];
        $allowedAttestation = ['none', 'indirect', 'direct', 'enterprise'];
        $allowedAttachment = ['', 'platform', 'cross-platform'];
        $allowedResidentKey = ['required', 'preferred', 'discouraged'];
        $allowedButtonDisplay = ['icon-text', 'icon-left', 'icon-only', 'text-only'];
        $validCoseIds = [-7, -257, -8, -35, -36, -258, -259];

        foreach ($fieldMap as $key => $constName) {
            if (\defined($constName) || !\array_key_exists($key, $input)) {
                continue;
            }

            // Validate requireUserVerification
            if ($key === 'requireUserVerification' && !\in_array($input[$key], $allowedUserVerification, true)) {
                continue;
            }

            // Validate algorithms
            if ($key === 'algorithms') {
                if (!\is_array($input[$key])) {
                    continue;
                }
                $algos = array_values(array_map('intval', $input[$key]));
                $invalid = array_diff($algos, $validCoseIds);
                if ($algos === [] || $invalid !== []) {
                    continue;
                }
                $saved[$key] = $algos;

                continue;
            }

            // Validate attestation
            if ($key === 'attestation' && !\in_array($input[$key], $allowedAttestation, true)) {
                continue;
            }

            // Validate authenticatorAttachment
            if ($key === 'authenticatorAttachment' && !\in_array($input[$key], $allowedAttachment, true)) {
                continue;
            }

            // Validate timeout (1000–300000 ms)
            if ($key === 'timeout') {
                $timeout = (int) $input[$key];
                if ($timeout < 1000 || $timeout > 300000) {
                    continue;
                }
                $saved[$key] = $timeout;

                continue;
            }

            // Validate residentKey
            if ($key === 'residentKey' && !\in_array($input[$key], $allowedResidentKey, true)) {
                continue;
            }

            // Validate buttonDisplay
            if ($key === 'buttonDisplay' && !\in_array($input[$key], $allowedButtonDisplay, true)) {
                continue;
            }

            // Validate maxCredentialsPerUser (1–20)
            if ($key === 'maxCredentialsPerUser') {
                $max = (int) $input[$key];
                if ($max < 1 || $max > 20) {
                    continue;
                }
                $saved[$key] = $max;

                continue;
            }

            $saved[$key] = $input[$key];
        }

        update_option(PasskeyLoginConfiguration::OPTION_NAME, $saved);
    }
}
