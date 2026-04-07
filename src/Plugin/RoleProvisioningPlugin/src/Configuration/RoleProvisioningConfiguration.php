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

namespace WpPack\Plugin\RoleProvisioningPlugin\Configuration;

final readonly class RoleProvisioningConfiguration
{
    public const OPTION_NAME = 'wppack_role_provisioning';

    /**
     * @param bool   $enabled      Whether role provisioning is active
     * @param bool   $addUserToBlog Auto-add new users to current site (multisite)
     * @param bool   $syncOnLogin  Re-evaluate rules on every SSO login
     * @param list<array{
     *     conditions: list<array{field: string, operator: string, value: string}>,
     *     role: string,
     *     blogIds: list<int>|null,
     * }> $rules Provisioning rules (evaluated top-down, first match wins)
     */
    public function __construct(
        public bool $enabled = true,
        public bool $addUserToBlog = false,
        public bool $syncOnLogin = false,
        public array $rules = [],
    ) {}

    /**
     * Load configuration from wp_options.
     */
    public static function fromOption(): self
    {
        $raw = get_option(self::OPTION_NAME, []);
        $saved = \is_array($raw) ? $raw : [];

        return new self(
            enabled: isset($saved['enabled']) ? (bool) $saved['enabled'] : true,
            addUserToBlog: isset($saved['addUserToBlog']) ? (bool) $saved['addUserToBlog'] : false,
            syncOnLogin: isset($saved['syncOnLogin']) ? (bool) $saved['syncOnLogin'] : false,
            rules: isset($saved['rules']) && \is_array($saved['rules']) ? self::normalizeRules($saved['rules']) : [],
        );
    }

    /**
     * @param array<int, mixed> $rules
     * @return list<array{conditions: list<array{field: string, operator: string, value: string}>, role: string, blogIds: list<int>|null}>
     */
    private static function normalizeRules(array $rules): array
    {
        $normalized = [];

        foreach ($rules as $rule) {
            if (!\is_array($rule)) {
                continue;
            }

            $conditions = [];
            if (isset($rule['conditions']) && \is_array($rule['conditions'])) {
                foreach ($rule['conditions'] as $condition) {
                    if (!\is_array($condition)) {
                        continue;
                    }

                    $conditions[] = [
                        'field' => (string) ($condition['field'] ?? ''),
                        'operator' => (string) ($condition['operator'] ?? 'equals'),
                        'value' => (string) ($condition['value'] ?? ''),
                    ];
                }
            }

            $blogIds = null;
            if (isset($rule['blogIds']) && \is_array($rule['blogIds'])) {
                $blogIds = array_values(array_map('intval', $rule['blogIds']));
            }

            $normalized[] = [
                'conditions' => $conditions,
                'role' => (string) ($rule['role'] ?? ''),
                'blogIds' => $blogIds,
            ];
        }

        return $normalized;
    }
}
