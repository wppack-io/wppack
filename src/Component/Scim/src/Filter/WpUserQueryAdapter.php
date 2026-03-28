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

namespace WpPack\Component\Scim\Filter;

use WpPack\Component\Scim\Exception\InvalidFilterException;
use WpPack\Component\Scim\Schema\ScimConstants;
use WpPack\Component\User\UserRepositoryInterface;

final readonly class WpUserQueryAdapter
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {}

    private const ATTRIBUTE_MAP = [
        'userName' => 'login',
        'displayName' => 'display_name',
        'emails.value' => 'email',
        'name.givenName' => 'first_name',
        'name.familyName' => 'last_name',
        'externalId' => ScimConstants::META_EXTERNAL_ID,
        'active' => ScimConstants::META_ACTIVE,
    ];

    private const META_ATTRIBUTES = [
        'externalId', 'active', 'name.givenName', 'name.familyName',
    ];

    /**
     * Convert a FilterNode AST to WP_User_Query arguments.
     *
     * @return array<string, mixed>
     */
    public function toQueryArgs(FilterNode $node): array
    {
        if ($node instanceof ComparisonNode) {
            return $this->comparisonToArgs($node);
        }

        if ($node instanceof LogicalNode) {
            return $this->logicalToArgs($node);
        }

        throw new InvalidFilterException('Unsupported filter node type.');
    }

    /**
     * @return array<string, mixed>
     */
    private function comparisonToArgs(ComparisonNode $node): array
    {
        $attribute = $node->attributePath;

        if (\in_array($attribute, self::META_ATTRIBUTES, true)) {
            return $this->metaComparison($node);
        }

        $wpField = self::ATTRIBUTE_MAP[$attribute] ?? null;
        if ($wpField === null) {
            throw new InvalidFilterException(sprintf('Unsupported filter attribute: "%s".', $attribute));
        }

        return match ($node->operator) {
            'eq' => $this->equalitySearch($wpField, $node->value),
            'co' => ['search' => '*' . $node->value . '*', 'search_columns' => [$this->toSearchColumn($wpField)]],
            'sw' => ['search' => $node->value . '*', 'search_columns' => [$this->toSearchColumn($wpField)]],
            'ew' => ['search' => '*' . $node->value, 'search_columns' => [$this->toSearchColumn($wpField)]],
            'ne' => ['exclude' => $this->findUserIdsByField($wpField, $node->value)],
            'pr' => [], // present — all users have these fields
            default => throw new InvalidFilterException(sprintf('Operator "%s" not supported for attribute "%s".', $node->operator, $attribute)),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function metaComparison(ComparisonNode $node): array
    {
        $metaKey = self::ATTRIBUTE_MAP[$node->attributePath] ?? $node->attributePath;
        $value = $this->resolveMetaValue($metaKey, $node->value);

        return match ($node->operator) {
            'eq' => [
                'meta_query' => [
                    ['key' => $metaKey, 'value' => $value, 'compare' => '='],
                ],
            ],
            'ne' => [
                'meta_query' => [
                    ['key' => $metaKey, 'value' => $value, 'compare' => '!='],
                ],
            ],
            'co' => [
                'meta_query' => [
                    ['key' => $metaKey, 'value' => $value, 'compare' => 'LIKE'],
                ],
            ],
            'sw' => [
                'meta_query' => [
                    ['key' => $metaKey, 'value' => '^' . preg_quote((string) $value, '/'), 'compare' => 'REGEXP'],
                ],
            ],
            'ew' => [
                'meta_query' => [
                    ['key' => $metaKey, 'value' => preg_quote((string) $value, '/') . '$', 'compare' => 'REGEXP'],
                ],
            ],
            'pr' => [
                'meta_query' => [
                    ['key' => $metaKey, 'compare' => 'EXISTS'],
                ],
            ],
            default => throw new InvalidFilterException(sprintf('Operator "%s" not supported for meta attribute "%s".', $node->operator, $node->attributePath)),
        };
    }

    private function resolveMetaValue(string $metaKey, ?string $value): ?string
    {
        if ($metaKey === ScimConstants::META_ACTIVE) {
            return match ($value) {
                'true' => '1',
                'false' => '0',
                default => $value,
            };
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    private function logicalToArgs(LogicalNode $node): array
    {
        $leftArgs = $this->toQueryArgs($node->left);
        $rightArgs = $this->toQueryArgs($node->right);

        // For "and", merge the meta_query arrays
        if ($node->operator === 'and') {
            return $this->mergeQueryArgs($leftArgs, $rightArgs, 'AND');
        }

        // For "or", we need meta_query with OR relation
        return $this->mergeQueryArgs($leftArgs, $rightArgs, 'OR');
    }

    /**
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     *
     * @return array<string, mixed>
     */
    private function mergeQueryArgs(array $left, array $right, string $relation): array
    {
        $leftMeta = $left['meta_query'] ?? [];
        $rightMeta = $right['meta_query'] ?? [];
        unset($left['meta_query'], $right['meta_query']);

        // OR on non-meta fields (search/search_columns/login) is not supported by WP_User_Query
        if ($relation === 'OR' && $left !== [] && $right !== []) {
            throw new InvalidFilterException('OR filter combining non-meta attributes is not supported.');
        }

        // AND with conflicting search params would silently discard one side via array_merge
        if ($relation === 'AND' && isset($left['search']) && isset($right['search'])) {
            throw new InvalidFilterException('AND filter combining multiple search attributes is not supported.');
        }

        $merged = array_merge($left, $right);

        if ($leftMeta !== [] || $rightMeta !== []) {
            $metaQuery = ['relation' => $relation];
            if ($leftMeta !== []) {
                $metaQuery[] = $leftMeta;
            }
            if ($rightMeta !== []) {
                $metaQuery[] = $rightMeta;
            }
            $merged['meta_query'] = $metaQuery;
        }

        return $merged;
    }

    /**
     * @return array<string, mixed>
     */
    private function equalitySearch(string $wpField, ?string $value): array
    {
        return match ($wpField) {
            'login' => ['login' => $value],
            'email' => ['search' => $value, 'search_columns' => ['user_email']],
            default => ['search' => $value, 'search_columns' => [$this->toSearchColumn($wpField)]],
        };
    }

    private function toSearchColumn(string $wpField): string
    {
        return match ($wpField) {
            'login' => 'user_login',
            'email' => 'user_email',
            'display_name' => 'display_name',
            default => $wpField,
        };
    }

    /**
     * @return list<int>
     */
    private function findUserIdsByField(string $wpField, ?string $value): array
    {
        $args = $this->equalitySearch($wpField, $value);
        $users = $this->userRepository->findAll($args);

        return array_map(static fn(\WP_User $user): int => $user->ID, $users);
    }
}
