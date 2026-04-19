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

namespace WPPack\Component\Query\Builder;

use WPPack\Component\Query\Condition\ConditionGroup;
use WPPack\Component\Query\Condition\OrderByGroup;
use WPPack\Component\Query\Enum\Order;
use WPPack\Component\Query\Result\UserQueryResult;
use WPPack\Component\Query\Wql\ExpressionParser;
use WPPack\Component\Query\Wql\WqlParser;

final class UserQueryBuilder
{
    private const PREFIX_MAP = [
        'm' => 'meta',
        'meta' => 'meta',
        'u' => 'user',
        'user' => 'user',
    ];

    private const ALLOWED_PREFIXES = ['meta', 'user'];

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

        // Resolve standard user field conditions
        $fieldArgs = $this->conditions->toFieldArgs('user', $this->parameters, $this->resolveUserField(...));
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
    private function resolveUserField(string $field, string $operator, mixed $value): array
    {
        return match ($field) {
            'role' => match ($operator) {
                '=' => ['role' => $value],
                'IN' => ['role__in' => $value],
                'NOT IN' => ['role__not_in' => $value],
                default => throw new \InvalidArgumentException(sprintf('Unsupported operator "%s" for field "user.role". Use "=", "IN", or "NOT IN".', $operator)),
            },
            'id' => match ($operator) {
                '=' => ['include' => [$value]],
                'IN' => ['include' => $value],
                'NOT IN' => ['exclude' => $value],
                default => throw new \InvalidArgumentException(sprintf('Unsupported operator "%s" for field "user.id". Use "=", "IN", or "NOT IN".', $operator)),
            },
            default => throw new \InvalidArgumentException(sprintf('Unknown user field "%s". Supported fields: role, id.', $field)),
        };
    }
}
