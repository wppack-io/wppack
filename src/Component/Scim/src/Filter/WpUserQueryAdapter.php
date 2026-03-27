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

final class WpUserQueryAdapter
{
    private const ATTRIBUTE_MAP = [
        'userName' => 'login',
        'displayName' => 'display_name',
        'name.givenName' => 'first_name',
        'name.familyName' => 'last_name',
        'emails.value' => 'email',
        'externalId' => '_wppack_scim_external_id',
        'active' => '_wppack_scim_active',
    ];

    private const META_ATTRIBUTES = [
        'externalId', 'active',
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

        return match ($node->operator) {
            'eq' => [
                'meta_query' => [
                    [
                        'key' => $metaKey,
                        'value' => $node->value,
                        'compare' => '=',
                    ],
                ],
            ],
            'ne' => [
                'meta_query' => [
                    [
                        'key' => $metaKey,
                        'value' => $node->value,
                        'compare' => '!=',
                    ],
                ],
            ],
            'pr' => [
                'meta_query' => [
                    [
                        'key' => $metaKey,
                        'compare' => 'EXISTS',
                    ],
                ],
            ],
            default => throw new InvalidFilterException(sprintf('Operator "%s" not supported for meta attribute "%s".', $node->operator, $node->attributePath)),
        };
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

        $merged = array_merge($left, $right);

        if ($leftMeta !== [] || $rightMeta !== []) {
            $metaQuery = ['relation' => $relation];
            foreach ($leftMeta as $clause) {
                if (!\is_array($clause) || isset($clause['relation'])) {
                    continue;
                }
                $metaQuery[] = $clause;
            }
            foreach ($rightMeta as $clause) {
                if (!\is_array($clause) || isset($clause['relation'])) {
                    continue;
                }
                $metaQuery[] = $clause;
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
        $args['fields'] = 'ID';
        $users = get_users($args);

        return array_map('intval', $users);
    }
}
