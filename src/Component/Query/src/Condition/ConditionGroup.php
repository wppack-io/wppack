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

namespace WPPack\Component\Query\Condition;

use WPPack\Component\Query\Wql\CompoundExpression;
use WPPack\Component\Query\Wql\ParsedExpression;
use WPPack\Component\Query\Wql\WqlParser;

final class ConditionGroup
{
    private readonly WqlParser $parser;

    /**
     * @var list<array{type: 'and'|'or', expression: ParsedExpression}|array{type: 'nested_and'|'nested_or', group: ConditionGroup}>
     */
    private array $entries = [];

    /**
     * @param list<string>|null $allowedPrefixes Restrict allowed prefixes (e.g., ['meta'] for User/Term builders)
     */
    public function __construct(
        private readonly ?array $allowedPrefixes = null,
        ?WqlParser $parser = null,
    ) {
        $this->parser = $parser ?? new WqlParser();
    }

    public function where(string $expression): self
    {
        return $this->addExpression('and', $expression);
    }

    public function andWhere(string|\Closure $expressionOrCallback): self
    {
        if ($expressionOrCallback instanceof \Closure) {
            $group = new self($this->allowedPrefixes, $this->parser);
            $expressionOrCallback($group);
            $this->entries[] = ['type' => 'nested_and', 'group' => $group];

            return $this;
        }

        return $this->addExpression('and', $expressionOrCallback);
    }

    public function orWhere(string|\Closure $expressionOrCallback): self
    {
        if ($expressionOrCallback instanceof \Closure) {
            $group = new self($this->allowedPrefixes, $this->parser);
            $expressionOrCallback($group);
            $this->entries[] = ['type' => 'nested_or', 'group' => $group];

            return $this;
        }

        return $this->addExpression('or', $expressionOrCallback);
    }

    /**
     * @param array<string, mixed> $parameters
     *
     * @return array<int|string, mixed>
     */
    public function toMetaQuery(array $parameters): array
    {
        return $this->toQuery('meta', $parameters);
    }

    /**
     * @param array<string, mixed> $parameters
     *
     * @return array<int|string, mixed>
     */
    public function toTaxQuery(array $parameters): array
    {
        return $this->toQuery('tax', $parameters);
    }

    /**
     * Convert standard field conditions to WP query args.
     *
     * @param callable(string $field, string $operator, mixed $value): array<string, mixed> $resolver
     * @param array<string, mixed> $parameters
     *
     * @return array<string, mixed>
     */
    public function toFieldArgs(string $prefix, array $parameters, callable $resolver): array
    {
        $args = [];

        foreach ($this->entries as $entry) {
            if ($entry['type'] === 'and' || $entry['type'] === 'or') {
                $expr = $entry['expression'];
                if ($expr->prefix !== $prefix) {
                    continue;
                }

                $value = $this->resolveFieldValue($expr, $parameters);
                $resolved = $resolver($expr->key, $expr->operator, $value);
                $args = array_merge($args, $resolved);
            } else {
                $nested = $entry['group']->toFieldArgs($prefix, $parameters, $resolver);
                $args = array_merge($args, $nested);
            }
        }

        return $args;
    }

    /**
     * Produce a WP meta/tax-query compatible array. The shape mixes the
     * literal `'relation'` string key with numeric-indexed sub-clauses.
     *
     * @param array<string, mixed> $parameters
     *
     * @return array<int|string, mixed>
     */
    private function toQuery(string $prefix, array $parameters): array
    {
        $andClauses = [];
        $orClauses = [];

        foreach ($this->entries as $entry) {
            match ($entry['type']) {
                'and' => $this->collectClause($entry['expression'], $prefix, $parameters, $andClauses),
                'or' => $this->collectClause($entry['expression'], $prefix, $parameters, $orClauses),
                'nested_and' => $this->collectNestedGroup($entry['group'], $prefix, $parameters, $andClauses),
                'nested_or' => $this->collectNestedGroup($entry['group'], $prefix, $parameters, $orClauses),
            };
        }

        $hasAnd = $andClauses !== [];
        $hasOr = $orClauses !== [];

        if (!$hasAnd && !$hasOr) {
            return [];
        }

        // AND only
        if ($hasAnd && !$hasOr) {
            return ['relation' => 'AND', ...$andClauses];
        }

        // OR only
        if (!$hasAnd) {
            return ['relation' => 'OR', ...$orClauses];
        }

        // Mixed: AND clauses + nested OR group
        $result = ['relation' => 'AND', ...$andClauses];
        $result[] = ['relation' => 'OR', ...$orClauses];

        return $result;
    }

    /**
     * @param array<string, mixed> $parameters
     * @param list<array<int|string, mixed>> $target
     */
    private function collectClause(ParsedExpression $expr, string $prefix, array $parameters, array &$target): void
    {
        if ($expr->prefix !== $prefix) {
            return;
        }

        $target[] = $this->buildClause($expr, $prefix, $parameters);
    }

    /**
     * @param array<string, mixed> $parameters
     * @param list<array<int|string, mixed>> $target
     */
    private function collectNestedGroup(self $group, string $prefix, array $parameters, array &$target): void
    {
        $nested = $group->toQuery($prefix, $parameters);
        if ($nested !== []) {
            $target[] = $nested;
        }
    }

    /**
     * @param array<string, mixed> $parameters
     *
     * @return array<string, mixed>
     */
    private function buildClause(ParsedExpression $expr, string $prefix, array $parameters): array
    {
        if ($prefix === 'meta') {
            return $this->buildMetaClause($expr, $parameters);
        }

        return $this->buildTaxClause($expr, $parameters);
    }

    /**
     * @param array<string, mixed> $parameters
     *
     * @return array<string, mixed>
     */
    private function buildMetaClause(ParsedExpression $expr, array $parameters): array
    {
        $clause = ['key' => $expr->key];

        if ($expr->operator === 'EXISTS' || $expr->operator === 'NOT EXISTS') {
            $clause['value'] = '';
            $clause['compare'] = $expr->operator;

            return $clause;
        }

        $value = $this->resolveParameter($expr->placeholder, $parameters);
        $clause['value'] = $value;
        $clause['compare'] = $expr->operator;

        if ($expr->hint !== null) {
            $clause['type'] = strtoupper($expr->hint);
        }

        return $clause;
    }

    /**
     * @param array<string, mixed> $parameters
     *
     * @return array<string, mixed>
     */
    private function buildTaxClause(ParsedExpression $expr, array $parameters): array
    {
        if ($expr->operator === 'EXISTS' || $expr->operator === 'NOT EXISTS') {
            return [
                'taxonomy' => $expr->key,
                'operator' => $expr->operator,
            ];
        }

        $terms = $this->resolveParameter($expr->placeholder, $parameters);

        return [
            'taxonomy' => $expr->key,
            'field' => $expr->hint ?? 'term_id',
            'terms' => $terms,
            'operator' => $expr->operator,
            'include_children' => true,
        ];
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function resolveParameter(?string $placeholder, array $parameters): mixed
    {
        if ($placeholder === null) {
            throw new \InvalidArgumentException('Placeholder is required for this operator.');
        }

        if (!\array_key_exists($placeholder, $parameters)) {
            throw new \InvalidArgumentException(sprintf('Parameter ":%s" is not set. Call setParameter(\'%s\', $value) to bind it.', $placeholder, $placeholder));
        }

        return $parameters[$placeholder];
    }

    /**
     * Resolve the value for a standard field expression.
     *
     * @param array<string, mixed> $parameters
     */
    private function resolveFieldValue(ParsedExpression $expr, array $parameters): mixed
    {
        if ($expr->operator === 'EXISTS' || $expr->operator === 'NOT EXISTS') {
            return null;
        }

        return $this->resolveParameter($expr->placeholder, $parameters);
    }

    /**
     * @param 'and'|'or' $type
     */
    private function addExpression(string $type, string $expression): self
    {
        $parsed = $this->parser->parse($expression);

        if ($parsed instanceof ParsedExpression) {
            $this->validatePrefix($parsed);
            $this->validateStandardFieldOrType($parsed, $type);
            $this->entries[] = ['type' => $type, 'expression' => $parsed];

            return $this;
        }

        // CompoundExpression: convert to nested ConditionGroup
        if (!$parsed instanceof CompoundExpression) {
            throw new \LogicException('Expected CompoundExpression.');
        }
        $group = $this->buildGroupFromCompound($parsed);
        $nestedType = ($type === 'and') ? 'nested_and' : 'nested_or';
        $this->entries[] = ['type' => $nestedType, 'group' => $group];

        return $this;
    }

    /**
     * Build a ConditionGroup from a CompoundExpression AST node recursively.
     */
    private function buildGroupFromCompound(CompoundExpression $compound): self
    {
        $group = new self($this->allowedPrefixes, $this->parser);
        $prefixes = [];

        foreach ($compound->children as $i => $child) {
            // First child is always 'and' type (base of the group)
            $childType = ($i === 0) ? 'and' : (($compound->operator === 'AND') ? 'and' : 'or');

            if ($child instanceof ParsedExpression) {
                $group->validatePrefix($child);
                $this->validateStandardFieldInCompound($child, $compound->operator);
                $group->entries[] = ['type' => $childType, 'expression' => $child];
                $prefixes[] = $child->prefix;
            } else {
                if (!$child instanceof CompoundExpression) {
                    throw new \LogicException('Expected CompoundExpression.');
                }
                $nested = $group->buildGroupFromCompound($child);
                $nestedType = ($childType === 'and') ? 'nested_and' : 'nested_or';
                $group->entries[] = ['type' => $nestedType, 'group' => $nested];
                $prefixes = [...$prefixes, ...$this->collectPrefixes($child)];
            }
        }

        // Validate: OR groups with mixed prefixes are not allowed
        if ($compound->operator === 'OR') {
            $uniquePrefixes = array_unique($prefixes);
            if (\count($uniquePrefixes) > 1) {
                throw new \InvalidArgumentException(sprintf(
                    'Cannot mix prefixes (%s) within an OR group. WordPress does not support cross-prefix OR conditions.',
                    implode(', ', $uniquePrefixes),
                ));
            }
        }

        return $group;
    }

    /**
     * Collect all leaf-node prefixes from a CompoundExpression.
     *
     * @return list<string>
     */
    private function collectPrefixes(CompoundExpression $compound): array
    {
        $prefixes = [];
        foreach ($compound->children as $child) {
            if ($child instanceof ParsedExpression) {
                $prefixes[] = $child->prefix;
            } else {
                if (!$child instanceof CompoundExpression) {
                    throw new \LogicException('Expected CompoundExpression.');
                }
                $prefixes = [...$prefixes, ...$this->collectPrefixes($child)];
            }
        }

        return $prefixes;
    }

    private function validatePrefix(ParsedExpression $expr): void
    {
        if ($this->allowedPrefixes !== null && !\in_array($expr->prefix, $this->allowedPrefixes, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Prefix "%s" is not allowed in this context. Allowed: %s.',
                $expr->prefix,
                implode(', ', $this->allowedPrefixes),
            ));
        }
    }

    /**
     * Validate that standard field prefixes are not used in OR conditions.
     */
    private function validateStandardFieldOrType(ParsedExpression $expr, string $type): void
    {
        if ($type === 'or' && $this->isStandardFieldPrefix($expr->prefix)) {
            throw new \InvalidArgumentException(sprintf(
                'Standard field "%s.%s" cannot be used in orWhere(). WordPress does not support OR conditions for standard fields.',
                $expr->prefix,
                $expr->key,
            ));
        }
    }

    /**
     * Validate that standard field prefixes are not used in compound OR expressions.
     */
    private function validateStandardFieldInCompound(ParsedExpression $expr, string $compoundOperator): void
    {
        if ($compoundOperator === 'OR' && $this->isStandardFieldPrefix($expr->prefix)) {
            throw new \InvalidArgumentException(sprintf(
                'Standard field "%s.%s" cannot be used in OR expressions. WordPress does not support OR conditions for standard fields.',
                $expr->prefix,
                $expr->key,
            ));
        }
    }

    private function isStandardFieldPrefix(string $prefix): bool
    {
        return \in_array($prefix, ['post', 'user', 'term'], true);
    }
}
