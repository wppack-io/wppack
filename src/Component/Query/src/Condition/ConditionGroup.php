<?php

declare(strict_types=1);

namespace WpPack\Component\Query\Condition;

use WpPack\Component\Query\Wql\CompoundExpression;
use WpPack\Component\Query\Wql\ExpressionNode;
use WpPack\Component\Query\Wql\ParsedExpression;
use WpPack\Component\Query\Wql\WqlParser;

final class ConditionGroup
{
    private static ?WqlParser $parser = null;

    /**
     * @var list<array{type: 'and'|'or', expression: ParsedExpression}|array{type: 'nested_and'|'nested_or', group: ConditionGroup}>
     */
    private array $entries = [];

    /**
     * @param list<string>|null $allowedPrefixes Restrict allowed prefixes (e.g., ['meta'] for User/Term builders)
     */
    public function __construct(
        private readonly ?array $allowedPrefixes = null,
    ) {}

    public function where(string $expression): self
    {
        return $this->addExpression('and', $expression);
    }

    public function andWhere(string|\Closure $expressionOrCallback): self
    {
        if ($expressionOrCallback instanceof \Closure) {
            $group = new self($this->allowedPrefixes);
            $expressionOrCallback($group);
            $this->entries[] = ['type' => 'nested_and', 'group' => $group];

            return $this;
        }

        return $this->addExpression('and', $expressionOrCallback);
    }

    public function orWhere(string|\Closure $expressionOrCallback): self
    {
        if ($expressionOrCallback instanceof \Closure) {
            $group = new self($this->allowedPrefixes);
            $expressionOrCallback($group);
            $this->entries[] = ['type' => 'nested_or', 'group' => $group];

            return $this;
        }

        return $this->addExpression('or', $expressionOrCallback);
    }

    /**
     * @param array<string, mixed> $parameters
     *
     * @return array<string, mixed>
     */
    public function toMetaQuery(array $parameters): array
    {
        return $this->toQuery('meta', $parameters);
    }

    /**
     * @param array<string, mixed> $parameters
     *
     * @return array<string, mixed>
     */
    public function toTaxQuery(array $parameters): array
    {
        return $this->toQuery('tax', $parameters);
    }

    /**
     * @param array<string, mixed> $parameters
     *
     * @return array<string, mixed>
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
     * @param list<array<string, mixed>> $target
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
     * @param list<array<string, mixed>> $target
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
     * @param 'and'|'or' $type
     */
    private function addExpression(string $type, string $expression): self
    {
        $parsed = self::getParser()->parse($expression);

        if ($parsed instanceof ParsedExpression) {
            $this->validatePrefix($parsed);
            $this->entries[] = ['type' => $type, 'expression' => $parsed];

            return $this;
        }

        // CompoundExpression: convert to nested ConditionGroup
        \assert($parsed instanceof CompoundExpression);
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
        $group = new self($this->allowedPrefixes);
        $prefixes = [];

        foreach ($compound->children as $i => $child) {
            // First child is always 'and' type (base of the group)
            $childType = ($i === 0) ? 'and' : (($compound->operator === 'AND') ? 'and' : 'or');

            if ($child instanceof ParsedExpression) {
                $group->validatePrefix($child);
                $group->entries[] = ['type' => $childType, 'expression' => $child];
                $prefixes[] = $child->prefix;
            } else {
                \assert($child instanceof CompoundExpression);
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
                \assert($child instanceof CompoundExpression);
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

    private static function getParser(): WqlParser
    {
        return self::$parser ??= new WqlParser();
    }
}
