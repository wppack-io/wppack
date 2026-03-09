<?php

declare(strict_types=1);

namespace WpPack\Component\Query\Builder;

use WpPack\Component\Query\Condition\ConditionGroup;
use WpPack\Component\Query\Enum\Order;
use WpPack\Component\Query\Result\TermQueryResult;

final class TermQueryBuilder
{
    /** @var array<string, mixed> */
    private array $args = [];

    private ConditionGroup $conditions;

    /** @var array<string, mixed> */
    private array $parameters = [];

    public function __construct()
    {
        $this->conditions = new ConditionGroup(allowedPrefixes: ['meta']);
    }

    /**
     * @param string|list<string> $taxonomy
     */
    public function taxonomy(string|array $taxonomy): self
    {
        $this->args['taxonomy'] = $taxonomy;

        return $this;
    }

    public function hideEmpty(bool $hideEmpty = true): self
    {
        $this->args['hide_empty'] = $hideEmpty;

        return $this;
    }

    /**
     * @param int|list<int> $id
     */
    public function id(int|array $id): self
    {
        if (\is_array($id)) {
            $this->args['include'] = $id;
        } else {
            $this->args['include'] = [$id];
        }

        return $this;
    }

    /**
     * @param list<int> $ids
     */
    public function notIn(array $ids): self
    {
        $this->args['exclude'] = $ids;

        return $this;
    }

    public function parent(int $parentId): self
    {
        $this->args['parent'] = $parentId;

        return $this;
    }

    public function childOf(int $termId): self
    {
        $this->args['child_of'] = $termId;

        return $this;
    }

    public function search(string $keyword): self
    {
        $this->args['search'] = $keyword;

        return $this;
    }

    /**
     * @param string|list<string> $slug
     */
    public function slug(string|array $slug): self
    {
        $this->args['slug'] = $slug;

        return $this;
    }

    // ── Conditions ──

    public function where(string $expression): self
    {
        $this->conditions->where($expression);

        return $this;
    }

    public function andWhere(string|\Closure $expressionOrCallback): self
    {
        $this->conditions->andWhere($expressionOrCallback);

        return $this;
    }

    public function orWhere(string|\Closure $expressionOrCallback): self
    {
        $this->conditions->orWhere($expressionOrCallback);

        return $this;
    }

    public function setParameter(string $name, mixed $value): self
    {
        $this->parameters[$name] = $value;

        return $this;
    }

    // ── Ordering ──

    public function orderBy(string $orderBy, Order|string $order = Order::Asc): self
    {
        $this->args['orderby'] = $orderBy;
        $this->args['order'] = $order instanceof Order ? $order->value : $order;

        return $this;
    }

    // ── Pagination ──

    public function limit(int $limit): self
    {
        $this->args['number'] = $limit;

        return $this;
    }

    public function offset(int $offset): self
    {
        $this->args['offset'] = $offset;

        return $this;
    }

    // ── Escape hatch ──

    public function arg(string $key, mixed $value): self
    {
        $this->args[$key] = $value;

        return $this;
    }

    // ── Execution methods ──

    public function get(): TermQueryResult
    {
        $query = new \WP_Term_Query($this->toArray());

        /** @var list<\WP_Term>|int|string $terms */
        $terms = $query->get_terms();

        if (!\is_array($terms)) {
            $terms = [];
        }

        // WP_Term_Query does not have a built-in total; use count query
        $totalArgs = $this->toArray();
        $totalArgs['fields'] = 'count';
        unset($totalArgs['number'], $totalArgs['offset']);
        /** @var int|string $total */
        $total = (new \WP_Term_Query($totalArgs))->get_terms();

        return new TermQueryResult(
            terms: $terms,
            total: (int) $total,
            wpTermQuery: $query,
        );
    }

    public function first(): ?\WP_Term
    {
        $this->args['number'] = 1;
        $query = new \WP_Term_Query($this->toArray());

        /** @var list<\WP_Term>|int|string $terms */
        $terms = $query->get_terms();

        if (!\is_array($terms)) {
            return null;
        }

        return $terms[0] ?? null;
    }

    /**
     * @return list<int>
     */
    public function getIds(): array
    {
        $this->args['fields'] = 'ids';
        $query = new \WP_Term_Query($this->toArray());

        /** @var list<int>|int|string $ids */
        $ids = $query->get_terms();

        if (!\is_array($ids)) {
            return [];
        }

        return $ids;
    }

    public function count(): int
    {
        $args = $this->toArray();
        $args['fields'] = 'count';
        $query = new \WP_Term_Query($args);

        /** @var int|string $count */
        $count = $query->get_terms();

        return (int) $count;
    }

    public function exists(): bool
    {
        $this->args['number'] = 1;
        $this->args['fields'] = 'ids';
        $query = new \WP_Term_Query($this->toArray());

        /** @var list<int>|int|string $result */
        $result = $query->get_terms();

        return \is_array($result) && $result !== [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $args = $this->args;

        $metaQuery = $this->conditions->toMetaQuery($this->parameters);
        if ($metaQuery !== []) {
            $args['meta_query'] = $metaQuery;
        }

        return $args;
    }
}
