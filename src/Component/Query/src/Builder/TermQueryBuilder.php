<?php

declare(strict_types=1);

namespace WpPack\Component\Query\Builder;

use WpPack\Component\Query\Condition\ConditionGroup;
use WpPack\Component\Query\Condition\OrderByGroup;
use WpPack\Component\Query\Enum\Order;
use WpPack\Component\Query\Result\TermQueryResult;
use WpPack\Component\Query\Wql\ExpressionParser;
use WpPack\Component\Query\Wql\WqlParser;

final class TermQueryBuilder
{
    private const PREFIX_MAP = [
        'm' => 'meta',
        'meta' => 'meta',
        't' => 'term',
        'term' => 'term',
    ];

    private const ALLOWED_PREFIXES = ['meta', 'term'];

    /** @var array<string, mixed> */
    private array $args = [];

    private ConditionGroup $conditions;

    private OrderByGroup $orderByGroup;

    /** @var array<string, mixed> */
    private array $parameters = [];

    public function __construct()
    {
        $parser = new WqlParser(new ExpressionParser(self::PREFIX_MAP));
        $this->conditions = new ConditionGroup(allowedPrefixes: self::ALLOWED_PREFIXES, parser: $parser);
        $this->orderByGroup = new OrderByGroup();
    }

    public function hideEmpty(bool $hideEmpty = true): self
    {
        $this->args['hide_empty'] = $hideEmpty;

        return $this;
    }

    public function search(string $keyword): self
    {
        $this->args['search'] = $keyword;

        return $this;
    }

    public function childOf(int $termId): self
    {
        $this->args['child_of'] = $termId;

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

    /**
     * @param array<string, mixed> $parameters
     */
    public function setParameters(array $parameters): self
    {
        foreach ($parameters as $name => $value) {
            $this->parameters[$name] = $value;
        }

        return $this;
    }

    // ── Ordering ──

    public function orderBy(string $orderBy, Order|string $order = Order::Asc): self
    {
        $direction = $order instanceof Order ? $order : Order::from($order);
        $this->orderByGroup->set($orderBy, $direction);

        return $this;
    }

    public function addOrderBy(string $orderBy, Order|string $order = Order::Asc): self
    {
        $direction = $order instanceof Order ? $order : Order::from($order);
        $this->orderByGroup->add($orderBy, $direction);

        return $this;
    }

    // ── Pagination ──

    public function setMaxResults(int $limit): self
    {
        $this->args['number'] = $limit;

        return $this;
    }

    public function setFirstResult(int $offset): self
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

        // Resolve standard term field conditions
        $fieldArgs = $this->conditions->toFieldArgs('term', $this->parameters, $this->resolveTermField(...));
        $args = array_merge($args, $fieldArgs);

        $metaQuery = $this->conditions->toMetaQuery($this->parameters);

        if (!$this->orderByGroup->isEmpty()) {
            $orderByArgs = $this->orderByGroup->toArgs($metaQuery);
            $args = array_merge($args, $orderByArgs);
        }

        if ($metaQuery !== []) {
            $args['meta_query'] = $metaQuery;
        }

        return $args;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveTermField(string $field, string $operator, mixed $value): array
    {
        return match ($field) {
            'taxonomy' => match ($operator) {
                '=', 'IN' => ['taxonomy' => $value],
                default => throw new \InvalidArgumentException(sprintf('Unsupported operator "%s" for field "term.taxonomy". Use "=" or "IN".', $operator)),
            },
            'id' => match ($operator) {
                '=' => ['include' => [$value]],
                'IN' => ['include' => $value],
                'NOT IN' => ['exclude' => $value],
                default => throw new \InvalidArgumentException(sprintf('Unsupported operator "%s" for field "term.id". Use "=", "IN", or "NOT IN".', $operator)),
            },
            'slug' => match ($operator) {
                '=', 'IN' => ['slug' => $value],
                default => throw new \InvalidArgumentException(sprintf('Unsupported operator "%s" for field "term.slug". Use "=" or "IN".', $operator)),
            },
            'parent' => match ($operator) {
                '=' => ['parent' => $value],
                default => throw new \InvalidArgumentException(sprintf('Unsupported operator "%s" for field "term.parent". Use "=".', $operator)),
            },
            default => throw new \InvalidArgumentException(sprintf('Unknown term field "%s". Supported fields: taxonomy, id, slug, parent.', $field)),
        };
    }
}
