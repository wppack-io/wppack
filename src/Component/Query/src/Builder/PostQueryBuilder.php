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
use WPPack\Component\Query\Result\PostQueryResult;
use WPPack\Component\Query\Wql\ExpressionParser;
use WPPack\Component\Query\Wql\WqlParser;

final class PostQueryBuilder
{
    private const PREFIX_MAP = [
        'm' => 'meta',
        'meta' => 'meta',
        't' => 'tax',
        'tax' => 'tax',
        'taxonomy' => 'tax',
        'p' => 'post',
        'post' => 'post',
    ];

    private const ALLOWED_PREFIXES = ['meta', 'tax', 'post'];

    /** @var array<string, mixed> */
    private array $args = [];

    private ConditionGroup $conditions;

    private OrderByGroup $orderByGroup;

    /** @var array<string, mixed> */
    private array $parameters = [];

    /** @var list<array<string, mixed>> */
    private array $dateQueries = [];

    public function __construct()
    {
        $parser = new WqlParser(new ExpressionParser(self::PREFIX_MAP));
        $this->conditions = new ConditionGroup(allowedPrefixes: self::ALLOWED_PREFIXES, parser: $parser);
        $this->orderByGroup = new OrderByGroup();
    }

    // ── Conditions (where/andWhere/orWhere) ──

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

    // ── Search ──

    public function search(string $keyword): self
    {
        $this->args['s'] = $keyword;

        return $this;
    }

    // ── Pagination ──

    public function setMaxResults(int $limit): self
    {
        $this->args['posts_per_page'] = $limit;

        return $this;
    }

    public function setFirstResult(int $offset): self
    {
        $this->args['offset'] = $offset;

        return $this;
    }

    // ── Ordering ──

    /**
     * @param string|array<string, string> $orderBy
     */
    public function orderBy(string|array $orderBy, Order|string $order = Order::Desc): self
    {
        if (\is_array($orderBy)) {
            $this->args['orderby'] = $orderBy;

            return $this;
        }

        $direction = $order instanceof Order ? $order : Order::from($order);
        $this->orderByGroup->set($orderBy, $direction);

        return $this;
    }

    public function addOrderBy(string $orderBy, Order|string $order = Order::Desc): self
    {
        $direction = $order instanceof Order ? $order : Order::from($order);
        $this->orderByGroup->add($orderBy, $direction);

        return $this;
    }

    // ── Date ──

    public function after(string $date): self
    {
        $this->dateQueries[] = ['after' => $date];

        return $this;
    }

    public function before(string $date): self
    {
        $this->dateQueries[] = ['before' => $date];

        return $this;
    }

    /**
     * @param array<string, mixed> $dateQuery
     */
    public function date(array $dateQuery): self
    {
        $this->dateQueries[] = $dateQuery;

        return $this;
    }

    // ── Performance ──

    public function noMetaCache(): self
    {
        $this->args['update_post_meta_cache'] = false;

        return $this;
    }

    public function noTermCache(): self
    {
        $this->args['update_post_term_cache'] = false;

        return $this;
    }

    public function withoutCount(): self
    {
        $this->args['no_found_rows'] = true;

        return $this;
    }

    // ── Escape hatch ──

    public function arg(string $key, mixed $value): self
    {
        $this->args[$key] = $value;

        return $this;
    }

    // ── Execution methods ──

    public function get(): PostQueryResult
    {
        $query = new \WP_Query($this->toArray());

        /** @var list<\WP_Post> $posts */
        $posts = $query->posts;
        $currentPage = (int) ($query->query_vars['paged'] ?? 1);

        return new PostQueryResult(
            posts: $posts,
            total: $query->found_posts,
            totalPages: (int) $query->max_num_pages,
            currentPage: max(1, $currentPage),
            wpQueryInstance: $query,
        );
    }

    public function first(): ?\WP_Post
    {
        $this->args['posts_per_page'] = 1;
        $this->args['no_found_rows'] = true;
        $query = new \WP_Query($this->toArray());

        /** @var list<\WP_Post> $posts */
        $posts = $query->posts;

        return $posts[0] ?? null;
    }

    /**
     * @return list<int>
     */
    public function getIds(): array
    {
        $this->args['fields'] = 'ids';
        $query = new \WP_Query($this->toArray());

        /** @var list<int> $ids */
        $ids = $query->posts;

        return $ids;
    }

    public function count(): int
    {
        $this->args['fields'] = 'ids';
        $this->args['posts_per_page'] = -1;
        $this->args['no_found_rows'] = true;
        $query = new \WP_Query($this->toArray());

        return $query->post_count;
    }

    public function exists(): bool
    {
        $this->args['fields'] = 'ids';
        $this->args['posts_per_page'] = 1;
        $this->args['no_found_rows'] = true;
        $query = new \WP_Query($this->toArray());

        return $query->post_count > 0;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $args = $this->args;

        // Resolve standard post field conditions
        $fieldArgs = $this->conditions->toFieldArgs('post', $this->parameters, $this->resolvePostField(...));
        $args = array_merge($args, $fieldArgs);

        $metaQuery = $this->conditions->toMetaQuery($this->parameters);

        if (!$this->orderByGroup->isEmpty()) {
            $orderByArgs = $this->orderByGroup->toArgs($metaQuery);
            $args = array_merge($args, $orderByArgs);
        }

        if ($metaQuery !== []) {
            $args['meta_query'] = $metaQuery;
        }

        $taxQuery = $this->conditions->toTaxQuery($this->parameters);
        if ($taxQuery !== []) {
            $args['tax_query'] = $taxQuery;
        }

        if ($this->dateQueries !== []) {
            $args['date_query'] = $this->dateQueries;
        }

        return $args;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolvePostField(string $field, string $operator, mixed $value): array
    {
        return match ($field) {
            'type' => match ($operator) {
                '=', 'IN' => ['post_type' => $value],
                default => throw new \InvalidArgumentException(sprintf('Unsupported operator "%s" for field "post.type". Use "=" or "IN".', $operator)),
            },
            'status' => match ($operator) {
                '=', 'IN' => ['post_status' => $value],
                default => throw new \InvalidArgumentException(sprintf('Unsupported operator "%s" for field "post.status". Use "=" or "IN".', $operator)),
            },
            'author' => match ($operator) {
                '=' => ['author' => $value],
                'IN' => ['author__in' => $value],
                'NOT IN' => ['author__not_in' => $value],
                default => throw new \InvalidArgumentException(sprintf('Unsupported operator "%s" for field "post.author". Use "=", "IN", or "NOT IN".', $operator)),
            },
            'id' => match ($operator) {
                '=' => ['p' => $value],
                'IN' => ['post__in' => $value],
                'NOT IN' => ['post__not_in' => $value],
                default => throw new \InvalidArgumentException(sprintf('Unsupported operator "%s" for field "post.id". Use "=", "IN", or "NOT IN".', $operator)),
            },
            'parent' => match ($operator) {
                '=' => ['post_parent' => $value],
                'IN' => ['post_parent__in' => $value],
                default => throw new \InvalidArgumentException(sprintf('Unsupported operator "%s" for field "post.parent". Use "=" or "IN".', $operator)),
            },
            default => throw new \InvalidArgumentException(sprintf('Unknown post field "%s". Supported fields: type, status, author, id, parent.', $field)),
        };
    }
}
