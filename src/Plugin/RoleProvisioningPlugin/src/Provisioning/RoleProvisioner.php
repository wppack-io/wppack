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

namespace WPPack\Plugin\RoleProvisioningPlugin\Provisioning;

use Psr\Log\LoggerInterface;
use WPPack\Component\Role\RoleProvider;
use WPPack\Component\Site\BlogContextInterface;
use WPPack\Component\User\UserRepositoryInterface;
use WPPack\Plugin\RoleProvisioningPlugin\Configuration\RoleProvisioningConfiguration;

final class RoleProvisioner
{
    /**
     * Valid comparison operators for rule conditions.
     *
     * @var list<string>
     */
    public const VALID_OPERATORS = ['equals', 'not_equals', 'contains', 'starts_with', 'ends_with', 'matches', 'exists'];

    /**
     * SSO meta key prefixes that trigger re-evaluation on login.
     */
    private const SSO_META_PREFIXES = [
        '_wppack_saml_attributes',
        '_wppack_oauth_claims_',
    ];

    public function __construct(
        private readonly RoleProvisioningConfiguration $configuration,
        private readonly RoleProvider $roleProvider,
        private readonly BlogContextInterface $blogContext,
        private readonly UserRepositoryInterface $userRepository,
        private readonly LoggerInterface $logger,
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

        if ($this->configuration->syncOnLogin) {
            add_action('updated_user_meta', [$this, 'onMetaUpdated'], 20, 4);
        }
    }

    /**
     * Handle updated_user_meta hook for syncOnLogin.
     *
     * Triggers re-evaluation when SSO-related meta keys are updated.
     */
    public function onMetaUpdated(int $metaId, int $userId, string $metaKey, mixed $metaValue): void
    {
        if (!$this->isSsoMetaKey($metaKey)) {
            return;
        }

        $this->logger->debug('SSO meta updated, re-evaluating provisioning rules', [
            'userId' => $userId,
            'metaKey' => $metaKey,
        ]);

        $this->provision($userId, isSync: true);
    }

    /**
     * Provision a user by evaluating rules and applying the matched role.
     *
     * Can be called externally to trigger role re-evaluation.
     */
    public function provision(int $userId, bool $isSync = false): void
    {
        $user = get_userdata($userId);

        if ($user === false) {
            return;
        }

        // Protected roles: never change these
        $currentRole = $user->roles[0] ?? '';
        if (\in_array($currentRole, $this->configuration->protectedRoles, true)) {
            $this->logger->debug('User has protected role, skipping provisioning', [
                'userId' => $userId,
                'role' => $currentRole,
            ]);

            return;
        }

        // On sync (not initial registration): protect manually changed roles
        if ($isSync) {
            $provisionedRole = $this->userRepository->getMeta($userId, '_wppack_provisioned_role', true);

            if ($provisionedRole === '' || $provisionedRole === false) {
                $this->logger->debug('No provisioned role recorded, skipping sync', [
                    'userId' => $userId,
                ]);

                return;
            }

            if ($currentRole !== $provisionedRole) {
                $this->logger->debug('Role was manually changed, skipping sync', [
                    'userId' => $userId,
                    'currentRole' => $currentRole,
                    'provisionedRole' => $provisionedRole,
                ]);

                return;
            }
        }

        $match = $this->evaluateRules($userId);

        if ($match === null) {
            $this->logger->debug('No provisioning rule matched for user', [
                'userId' => $userId,
            ]);

            return;
        }

        $role = $this->resolveRole($match['role'], $userId);
        $blogIds = $match['blogIds'];

        // Validate role exists, fall back to default_role if invalid
        if ($this->roleProvider->find($role) === null) {
            $this->logger->warning('Matched role does not exist, falling back to default_role', [
                'userId' => $userId,
                'requestedRole' => $role,
            ]);
            $role = get_option('default_role', 'subscriber');
        }

        $this->logger->info('Provisioning rule matched', [
            'userId' => $userId,
            'role' => $role,
            'blogIds' => $blogIds,
        ]);

        if ($blogIds !== null && $this->blogContext->isMultisite()) {
            foreach ($blogIds as $blogId) {
                if (!function_exists('get_blog_details') || get_blog_details($blogId) === false) {
                    $this->logger->warning('Blog does not exist, skipping assignment', [
                        'userId' => $userId,
                        'blogId' => $blogId,
                    ]);

                    continue;
                }

                add_user_to_blog($blogId, $userId, $role);

                $this->logger->info('Added user to blog', [
                    'userId' => $userId,
                    'blogId' => $blogId,
                    'role' => $role,
                ]);
            }
        } else {
            $user->set_role($role);

            if ($this->configuration->addUserToBlog && $this->blogContext->isMultisite()) {
                add_user_to_blog($this->blogContext->getMainSiteId(), $userId, $role);
            }
        }

        // Record the provisioned role for manual-change detection
        $this->userRepository->updateMeta($userId, '_wppack_provisioned_role', $role);
    }

    /**
     * Check whether a meta key is an SSO-related key.
     */
    private function isSsoMetaKey(string $metaKey): bool
    {
        foreach (self::SSO_META_PREFIXES as $prefix) {
            if (str_starts_with($metaKey, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Handle user_register hook.
     */
    public function onUserRegister(int $userId): void
    {
        $this->provision($userId);
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

        foreach ($this->configuration->rules as $index => $rule) {
            if ($this->matchesRule($rule, $user, $userId)) {
                $this->logger->debug('Provisioning rule matched', [
                    'userId' => $userId,
                    'ruleIndex' => $index,
                    'role' => $rule['role'],
                    'conditionCount' => \count($rule['conditions']),
                ]);

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
            'matches' => $this->matchesRegex($expected, $fieldValue),
            'exists' => $fieldValue !== '',
        };
    }

    /**
     * Match a value against a regex pattern with proper error handling.
     */
    private function matchesRegex(string $pattern, string $subject): bool
    {
        $result = preg_match($pattern, $subject);

        if ($result === false) {
            $this->logger->warning('Invalid regex pattern in provisioning rule', [
                'pattern' => $pattern,
                'error' => preg_last_error_msg(),
            ]);

            return false;
        }

        return $result === 1;
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
