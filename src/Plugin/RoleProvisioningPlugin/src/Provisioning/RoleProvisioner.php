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

namespace WpPack\Plugin\RoleProvisioningPlugin\Provisioning;

use WpPack\Component\Role\RoleProvider;
use WpPack\Component\Site\BlogContextInterface;
use WpPack\Plugin\RoleProvisioningPlugin\Configuration\RoleProvisioningConfiguration;

final class RoleProvisioner
{
    private const VALID_OPERATORS = ['equals', 'not_equals', 'contains', 'starts_with', 'ends_with', 'matches', 'exists'];

    public function __construct(
        private readonly RoleProvisioningConfiguration $configuration,
        private readonly RoleProvider $roleProvider,
        private readonly BlogContextInterface $blogContext,
    ) {}

    /**
     * Register WordPress hooks for role provisioning.
     */
    public function register(): void
    {
        if (!$this->configuration->enabled) {
            return;
        }

        add_action('user_register', [$this, 'onUserRegister'], 20);
    }

    /**
     * Handle user_register hook.
     */
    public function onUserRegister(int $userId): void
    {
        $match = $this->evaluateRules($userId);

        if ($match === null) {
            return;
        }

        $role = $this->resolveRole($match['role'], $userId);
        $blogIds = $match['blogIds'];

        // Validate role exists, fall back to default_role if invalid
        if ($this->roleProvider->find($role) === null) {
            $role = get_option('default_role', 'subscriber');
        }

        if ($blogIds !== null && $this->blogContext->isMultisite()) {
            // Apply to specific blogs
            foreach ($blogIds as $blogId) {
                add_user_to_blog($blogId, $userId, $role);
            }
        } else {
            // Apply to current site
            $user = get_userdata($userId);

            if ($user !== false) {
                $user->set_role($role);
            }

            if ($this->configuration->addUserToBlog && $this->blogContext->isMultisite()) {
                add_user_to_blog(get_current_blog_id(), $userId, $role);
            }
        }
    }

    /**
     * Evaluate provisioning rules for a user.
     * Returns the first matching rule or null.
     *
     * @return array{conditions: list<array{field: string, operator: string, value: string}>, role: string, blogIds: list<int>|null}|null
     */
    public function evaluateRules(int $userId): ?array
    {
        $user = get_userdata($userId);

        if ($user === false) {
            return null;
        }

        foreach ($this->configuration->rules as $rule) {
            if ($this->matchesRule($rule, $user, $userId)) {
                return $rule;
            }
        }

        return null;
    }

    /**
     * Check if a user matches all conditions in a rule (AND logic).
     *
     * @param array{conditions: list<array{field: string, operator: string, value: string}>, role: string, blogIds: list<int>|null} $rule
     */
    private function matchesRule(array $rule, \WP_User $user, int $userId): bool
    {
        if ($rule['conditions'] === []) {
            return false;
        }

        foreach ($rule['conditions'] as $condition) {
            if (!$this->matchesCondition($condition, $user, $userId)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Evaluate a single condition against a user.
     *
     * @param array{field: string, operator: string, value: string} $condition
     */
    private function matchesCondition(array $condition, \WP_User $user, int $userId): bool
    {
        $fieldValue = $this->resolveField($condition['field'], $user, $userId);
        $operator = $condition['operator'];
        $expected = $condition['value'];

        if (!\in_array($operator, self::VALID_OPERATORS, true)) {
            return false;
        }

        return match ($operator) {
            'equals' => $fieldValue === $expected,
            'not_equals' => $fieldValue !== $expected,
            'contains' => str_contains($fieldValue, $expected),
            'starts_with' => str_starts_with($fieldValue, $expected),
            'ends_with' => str_ends_with($fieldValue, $expected),
            'matches' => @preg_match($expected, $fieldValue) === 1,
            'exists' => $fieldValue !== '',
        };
    }

    /**
     * Resolve a field reference to its value.
     *
     * Supported formats:
     * - user.email, user.login, user.display_name, etc. -> WP_User fields
     * - meta.<key> -> user meta value
     * - meta.<key>.<path> -> JSON dot-path into user meta value
     */
    private function resolveField(string $field, \WP_User $user, int $userId): string
    {
        if (str_starts_with($field, 'user.')) {
            return $this->resolveUserField(substr($field, 5), $user);
        }

        if (str_starts_with($field, 'meta.')) {
            return $this->resolveMetaField(substr($field, 5), $userId);
        }

        return '';
    }

    /**
     * Resolve a WP_User property.
     */
    private function resolveUserField(string $property, \WP_User $user): string
    {
        $value = match ($property) {
            'email' => $user->user_email,
            'login' => $user->user_login,
            'display_name' => $user->display_name,
            'nicename' => $user->user_nicename,
            'url' => $user->user_url,
            default => $user->get($property),
        };

        return \is_string($value) ? $value : '';
    }

    /**
     * Resolve a user meta field, supporting JSON dot-path.
     *
     * Examples:
     * - meta.department -> get_user_meta($userId, 'department', true)
     * - meta.saml_attributes.groups.0 -> JSON dot-path into saml_attributes
     */
    private function resolveMetaField(string $path, int $userId): string
    {
        $dotPos = strpos($path, '.');
        $metaKey = $dotPos !== false ? substr($path, 0, $dotPos) : $path;
        $remaining = $dotPos !== false ? substr($path, $dotPos + 1) : '';

        if ($metaKey === '') {
            return '';
        }

        $metaValue = get_user_meta($userId, $metaKey, true);

        // No dot-path: return the raw meta value
        if ($remaining === '') {
            return \is_string($metaValue) ? $metaValue : '';
        }

        // JSON dot-path: decode if string, then traverse
        $data = $metaValue;
        if (\is_string($data)) {
            $decoded = json_decode($data, true);
            if (\is_array($decoded)) {
                $data = $decoded;
            }
        }

        if (!\is_array($data)) {
            return '';
        }

        $segments = explode('.', $remaining);

        return $this->traverseDotPath($data, $segments);
    }

    /**
     * Traverse an array using dot-path segments.
     *
     * @param array<string|int, mixed> $data
     * @param list<string> $segments
     */
    private function traverseDotPath(array $data, array $segments): string
    {
        $current = $data;

        foreach ($segments as $segment) {
            if (\is_array($current) && \array_key_exists($segment, $current)) {
                $current = $current[$segment];
            } elseif (\is_array($current) && ctype_digit($segment) && \array_key_exists((int) $segment, $current)) {
                $current = $current[(int) $segment];
            } else {
                return '';
            }
        }

        if (\is_string($current)) {
            return $current;
        }

        if (\is_int($current) || \is_float($current)) {
            return (string) $current;
        }

        if (\is_bool($current)) {
            return $current ? '1' : '0';
        }

        return '';
    }

    /**
     * Resolve a role template, replacing {{meta.<key>.<path>}} placeholders.
     */
    private function resolveRole(string $role, int $userId): string
    {
        if (!str_contains($role, '{{')) {
            return $role;
        }

        return (string) preg_replace_callback('/\{\{(meta\.[^}]+)\}\}/', function (array $matches) use ($userId): string {
            $user = get_userdata($userId);

            if ($user === false) {
                return '';
            }

            return $this->resolveField($matches[1], $user, $userId);
        }, $role);
    }
}
