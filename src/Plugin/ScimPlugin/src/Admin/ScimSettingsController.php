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

namespace WpPack\Plugin\ScimPlugin\Admin;

use WpPack\Component\HttpFoundation\JsonResponse;
use WpPack\Component\Rest\AbstractRestController;
use WpPack\Component\Rest\Attribute\RestRoute;
use WpPack\Component\Rest\HttpMethod;
use WpPack\Component\Role\Attribute\IsGranted;
use WpPack\Component\Role\RoleProvider;
use WpPack\Plugin\ScimPlugin\Configuration\ScimConfiguration;

#[RestRoute(namespace: 'wppack/v1/scim')]
#[IsGranted('manage_options')]
final class ScimSettingsController extends AbstractRestController
{
    public function __construct(
        private readonly RoleProvider $roleProvider,
    ) {}

    #[RestRoute(route: '/settings', methods: HttpMethod::GET)]
    public function getSettings(): JsonResponse
    {
        return $this->json($this->buildResponse());
    }

    #[RestRoute(route: '/settings', methods: HttpMethod::POST)]
    public function saveSettings(\WP_REST_Request $request): JsonResponse
    {
        /** @var array<string, mixed> $params */
        $params = $request->get_json_params();

        $this->persistOptions($params);

        delete_option('rewrite_rules');

        return $this->json($this->buildResponse());
    }

    /**
     * @return array<string, mixed>
     */
    private function buildResponse(): array
    {
        $raw = get_option(ScimConfiguration::OPTION_NAME, []);
        $saved = \is_array($raw) ? $raw : [];

        $fields = [
            'bearerToken' => ['const' => 'SCIM_BEARER_TOKEN', 'default' => '', 'sensitive' => true],
            'autoProvision' => ['const' => 'SCIM_AUTO_PROVISION', 'default' => true],
            'defaultRole' => ['const' => 'SCIM_DEFAULT_ROLE', 'default' => 'subscriber'],
            'allowGroupManagement' => ['const' => 'SCIM_ALLOW_GROUP_MANAGEMENT', 'default' => true],
            'allowUserDeletion' => ['const' => 'SCIM_ALLOW_USER_DELETION', 'default' => false],
            'maxResults' => ['const' => 'SCIM_MAX_RESULTS', 'default' => 100],
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

            // Mask sensitive fields
            if (isset($meta['sensitive']) && $value !== '' && $value !== false) {
                $value = ScimConfiguration::MASKED_VALUE;
            }

            // Cast booleans
            if (\is_bool($meta['default'])) {
                $value = filter_var($value, \FILTER_VALIDATE_BOOLEAN);
            }

            // Cast integers
            if (\is_int($meta['default'])) {
                $value = (int) $value;
            }

            $settings[$key] = [
                'value' => $value,
                'source' => $source,
                'readonly' => $source === 'constant',
            ];
        }

        $blogId = is_multisite() ? get_main_site_id() : null;

        return [
            'settings' => $settings,
            'baseUrl' => rtrim(get_rest_url($blogId), '/'),
            'roles' => $this->roleProvider->getNames(),
        ];
    }

    /**
     * @param array<string, mixed> $input
     */
    private function persistOptions(array $input): void
    {
        $raw = get_option(ScimConfiguration::OPTION_NAME, []);
        $saved = \is_array($raw) ? $raw : [];

        $fieldMap = [
            'bearerToken' => 'SCIM_BEARER_TOKEN',
            'autoProvision' => 'SCIM_AUTO_PROVISION',
            'defaultRole' => 'SCIM_DEFAULT_ROLE',
            'allowGroupManagement' => 'SCIM_ALLOW_GROUP_MANAGEMENT',
            'allowUserDeletion' => 'SCIM_ALLOW_USER_DELETION',
            'maxResults' => 'SCIM_MAX_RESULTS',
        ];

        foreach ($fieldMap as $key => $constName) {
            if (\defined($constName) || !\array_key_exists($key, $input)) {
                continue;
            }

            // Skip masked token
            if ($key === 'bearerToken' && $input[$key] === ScimConfiguration::MASKED_VALUE) {
                continue;
            }

            // Validate role
            if ($key === 'defaultRole' && \is_string($input[$key])) {
                $roles = $this->roleProvider->getNames();
                if (!isset($roles[$input[$key]])) {
                    continue;
                }
            }

            // Validate maxResults
            if ($key === 'maxResults') {
                $input[$key] = max(1, min(1000, (int) $input[$key]));
            }

            $saved[$key] = $input[$key];
        }

        update_option(ScimConfiguration::OPTION_NAME, $saved);
    }
}
