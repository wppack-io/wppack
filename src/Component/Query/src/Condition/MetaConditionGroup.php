<?php

declare(strict_types=1);

namespace WpPack\Component\Query\Condition;

use WpPack\Component\Query\Enum\MetaCompare;
use WpPack\Component\Query\Enum\MetaType;

final class MetaConditionGroup
{
    /**
     * @var list<array{type: 'and'|'or', clause: array{key: string, value: mixed, compare: string, type?: string}}|array{type: 'nested_and'|'nested_or', group: MetaConditionGroup}>
     */
    private array $entries = [];

    public function where(string $key, mixed $value = null, MetaCompare|string $compare = '=', MetaType|string|null $type = null): self
    {
        return $this->andWhere($key, $value, $compare, $type);
    }

    public function andWhere(string|\Closure $keyOrGroup, mixed $value = null, MetaCompare|string $compare = '=', MetaType|string|null $type = null): self
    {
        if ($keyOrGroup instanceof \Closure) {
            $group = new self();
            $keyOrGroup($group);
            $this->entries[] = ['type' => 'nested_and', 'group' => $group];

            return $this;
        }

        $this->entries[] = ['type' => 'and', 'clause' => $this->buildClause($keyOrGroup, $value, $compare, $type)];

        return $this;
    }

    public function orWhere(string|\Closure $keyOrGroup, mixed $value = null, MetaCompare|string $compare = '=', MetaType|string|null $type = null): self
    {
        if ($keyOrGroup instanceof \Closure) {
            $group = new self();
            $keyOrGroup($group);
            $this->entries[] = ['type' => 'nested_or', 'group' => $group];

            return $this;
        }

        $this->entries[] = ['type' => 'or', 'clause' => $this->buildClause($keyOrGroup, $value, $compare, $type)];

        return $this;
    }

    public function whereExists(string $key): self
    {
        $this->entries[] = [
            'type' => 'and',
            'clause' => [
                'key' => $key,
                'value' => '',
                'compare' => MetaCompare::Exists->value,
            ],
        ];

        return $this;
    }

    public function whereNotExists(string $key): self
    {
        $this->entries[] = [
            'type' => 'and',
            'clause' => [
                'key' => $key,
                'value' => '',
                'compare' => MetaCompare::NotExists->value,
            ],
        ];

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toMetaQuery(): array
    {
        if ($this->entries === []) {
            return [];
        }

        $andClauses = [];
        $orClauses = [];

        foreach ($this->entries as $entry) {
            match ($entry['type']) {
                'and' => $andClauses[] = $entry['clause'],
                'or' => $orClauses[] = $entry['clause'],
                'nested_and' => $andClauses[] = $entry['group']->toMetaQuery(),
                'nested_or' => $orClauses[] = $entry['group']->toMetaQuery(),
            };
        }

        $hasAnd = $andClauses !== [];
        $hasOr = $orClauses !== [];

        // AND only
        if ($hasAnd && !$hasOr) {
            return ['relation' => 'AND', ...$andClauses];
        }

        // OR only
        if (!$hasAnd && $hasOr) {
            return ['relation' => 'OR', ...$orClauses];
        }

        // Mixed: AND clauses + nested OR group
        $result = ['relation' => 'AND', ...$andClauses];
        $result[] = ['relation' => 'OR', ...$orClauses];

        return $result;
    }

    /**
     * @return array{key: string, value: mixed, compare: string, type?: string}
     */
    private function buildClause(string $key, mixed $value, MetaCompare|string $compare, MetaType|string|null $type): array
    {
        $clause = [
            'key' => $key,
            'value' => $value,
            'compare' => $compare instanceof MetaCompare ? $compare->value : $compare,
        ];

        if ($type !== null) {
            $clause['type'] = $type instanceof MetaType ? $type->value : $type;
        }

        return $clause;
    }
}
