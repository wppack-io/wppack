<?php

declare(strict_types=1);

namespace WpPack\Component\Query\Builder;

use WpPack\Component\Query\Condition\ConditionGroup;
use WpPack\Component\Query\Enum\Order;
use WpPack\Component\Query\Result\UserQueryResult;

final class UserQueryBuilder
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
     * @param string|list<string> $role
     */
    public function role(string|array $role): self
    {
        if (\is_array($role)) {
            $this->args['role__in'] = $role;
        } else {
            $this->args['role'] = $role;
        }

        return $this;
    }

    /**
     * @param list<string> $roles
     */
    public function roleNotIn(array $roles): self
    {
        $this->args['role__not_in'] = $roles;

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

    public function search(string $keyword): self
    {
        $this->args['search'] = '*' . $keyword . '*';

        return $this;
    }

    /**
     * @param string|list<string> $postType
     */
    public function hasPublishedPosts(string|array|bool $postType = true): self
    {
        $this->args['has_published_posts'] = $postType;

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

    public function page(int $page): self
    {
        $this->args['paged'] = $page;

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

    public function get(): UserQueryResult
    {
        $this->args['count_total'] = true;
        $query = new \WP_User_Query($this->toArray());

        /** @var list<\WP_User> $users */
        $users = $query->get_results();
        $total = $query->get_total();
        $perPage = (int) ($this->args['number'] ?? 0);
        $currentPage = (int) ($this->args['paged'] ?? 1);
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : ($total > 0 ? 1 : 0);

        return new UserQueryResult(
            users: $users,
            total: $total,
            totalPages: $totalPages,
            currentPage: max(1, $currentPage),
            wpUserQuery: $query,
        );
    }

    public function first(): ?\WP_User
    {
        $this->args['number'] = 1;
        $this->args['count_total'] = false;
        $query = new \WP_User_Query($this->toArray());

        /** @var list<\WP_User> $users */
        $users = $query->get_results();

        return $users[0] ?? null;
    }

    /**
     * @return list<int>
     */
    public function getIds(): array
    {
        $this->args['fields'] = 'ID';
        $this->args['count_total'] = false;
        $query = new \WP_User_Query($this->toArray());

        /** @var list<int|string> $results */
        $results = $query->get_results();

        return array_map(intval(...), $results);
    }

    public function count(): int
    {
        $this->args['count_total'] = true;
        $this->args['fields'] = 'ID';
        $this->args['number'] = 1;
        $query = new \WP_User_Query($this->toArray());

        return $query->get_total();
    }

    public function exists(): bool
    {
        return $this->count() > 0;
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
