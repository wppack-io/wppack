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

namespace WpPack\Plugin\RoleProvisioningPlugin\Admin;

use WpPack\Component\HttpFoundation\JsonResponse;
use WpPack\Component\Rest\AbstractRestController;
use WpPack\Component\Rest\Attribute\RestRoute;
use WpPack\Component\Rest\HttpMethod;
use WpPack\Component\Role\Attribute\IsGranted;
use WpPack\Component\Role\RoleProvider;
use WpPack\Plugin\RoleProvisioningPlugin\Configuration\RoleProvisioningConfiguration;
use WpPack\Plugin\RoleProvisioningPlugin\Provisioning\RoleProvisioner;

#[RestRoute(namespace: 'wppack/v1/role-provisioning')]
#[IsGranted('manage_options')]
final class RoleProvisioningSettingsController extends AbstractRestController
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

        return $this->json($this->buildResponse());
    }

    /**
     * @return array<string, mixed>
     */
    private function buildResponse(): array
    {
        $raw = get_option(RoleProvisioningConfiguration::OPTION_NAME, []);
        $saved = \is_array($raw) ? $raw : [];

        return [
            'settings' => [
                'enabled' => [
                    'value' => isset($saved['enabled']) ? (bool) $saved['enabled'] : true,
                    'source' => \array_key_exists('enabled', $saved) ? 'option' : 'default',
                ],
                'addUserToBlog' => [
                    'value' => isset($saved['addUserToBlog']) ? (bool) $saved['addUserToBlog'] : false,
                    'source' => \array_key_exists('addUserToBlog', $saved) ? 'option' : 'default',
                ],
                'syncOnLogin' => [
                    'value' => isset($saved['syncOnLogin']) ? (bool) $saved['syncOnLogin'] : false,
                    'source' => \array_key_exists('syncOnLogin', $saved) ? 'option' : 'default',
                ],
                'rules' => [
                    'value' => isset($saved['rules']) && \is_array($saved['rules']) ? $saved['rules'] : [],
                    'source' => \array_key_exists('rules', $saved) ? 'option' : 'default',
                ],
            ],
            'roles' => $this->roleProvider->getNames(),
            'isMultisite' => is_multisite(),
            'sites' => $this->getSites(),
        ];
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function getSites(): array
    {
        if (!is_multisite() || !function_exists('get_sites')) {
            return [];
        }

        $sites = [];

        foreach (get_sites(['number' => 100]) as $site) {
            $blogId = (int) $site->blog_id;
            $name = get_blog_option($blogId, 'blogname') ?: $site->domain . $site->path;
            $sites[] = [
                'id' => $blogId,
                'name' => $name,
            ];
        }

        return $sites;
    }

    /**
     * @param array<string, mixed> $input
     */
    private function persistOptions(array $input): void
    {
        $raw = get_option(RoleProvisioningConfiguration::OPTION_NAME, []);
        $saved = \is_array($raw) ? $raw : [];

        // Boolean fields
        foreach (['enabled', 'addUserToBlog', 'syncOnLogin'] as $key) {
            if (\array_key_exists($key, $input)) {
                $saved[$key] = (bool) $input[$key];
            }
        }

        // Rules
        if (\array_key_exists('rules', $input) && \is_array($input['rules'])) {
            $saved['rules'] = $this->validateRules($input['rules']);
        }

        update_option(RoleProvisioningConfiguration::OPTION_NAME, $saved);
    }

    /**
     * @param array<int, mixed> $rules
     * @return list<array{conditions: list<array{field: string, operator: string, value: string}>, role: string, blogIds: list<int>|null}>
     */
    private function validateRules(array $rules): array
    {
        $validated = [];

        foreach ($rules as $rule) {
            if (!\is_array($rule)) {
                continue;
            }

            $role = (string) ($rule['role'] ?? '');
            if ($role === '') {
                continue;
            }

            $conditions = [];
            if (isset($rule['conditions']) && \is_array($rule['conditions'])) {
                foreach ($rule['conditions'] as $condition) {
                    if (!\is_array($condition)) {
                        continue;
                    }

                    $field = (string) ($condition['field'] ?? '');
                    $operator = (string) ($condition['operator'] ?? 'equals');
                    $value = (string) ($condition['value'] ?? '');

                    if ($field === '') {
                        continue;
                    }

                    if (!\in_array($operator, RoleProvisioner::VALID_OPERATORS, true)) {
                        continue;
                    }

                    // Validate regex pattern for matches operator
                    if ($operator === 'matches' && preg_match($value, '') === false) {
                        continue;
                    }

                    $conditions[] = [
                        'field' => $field,
                        'operator' => $operator,
                        'value' => $value,
                    ];
                }
            }

            // Reject rules with no valid conditions
            if ($conditions === []) {
                continue;
            }

            $blogIds = null;
            if (isset($rule['blogIds']) && \is_array($rule['blogIds'])) {
                $blogIds = array_values(array_map('intval', $rule['blogIds']));
            }

            $validated[] = [
                'conditions' => $conditions,
                'role' => $role,
                'blogIds' => $blogIds,
            ];
        }

        return $validated;
    }
}
