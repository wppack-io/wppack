<?php

declare(strict_types=1);

namespace WpPack\Component\Query\Builder;

use WpPack\Component\Query\Condition\ConditionGroup;
use WpPack\Component\Query\Enum\MetaType;
use WpPack\Component\Query\Enum\Order;
use WpPack\Component\Query\Enum\PostStatus;
use WpPack\Component\Query\Result\PostQueryResult;

final class PostQueryBuilder
{
    /** @var array<string, mixed> */
    private array $args = [];

    private ConditionGroup $conditions;

    /** @var array<string, mixed> */
    private array $parameters = [];

    /** @var list<array<string, mixed>> */
    private array $dateQueries = [];

    public function __construct()
    {
        $this->conditions = new ConditionGroup();
    }

    /**
     * @param string|list<string> $postType
     */
    public function type(string|array $postType): self
    {
        $this->args['post_type'] = $postType;

        return $this;
    }

    /**
     * @param PostStatus|string|list<PostStatus|string> $status
     */
    public function status(PostStatus|string|array $status): self
    {
        if (\is_array($status)) {
            $this->args['post_status'] = array_map(
                static fn(PostStatus|string $s): string => $s instanceof PostStatus ? $s->value : $s,
                $status,
            );
        } else {
            $this->args['post_status'] = $status instanceof PostStatus ? $status->value : $status;
        }

        return $this;
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

    // ── Author ──

    /**
     * @param int|list<int> $author
     */
    public function author(int|array $author): self
    {
        if (\is_array($author)) {
            $this->args['author__in'] = $author;
        } else {
            $this->args['author'] = $author;
        }

        return $this;
    }

    /**
     * @param list<int> $authorIds
     */
    public function authorNotIn(array $authorIds): self
    {
        $this->args['author__not_in'] = $authorIds;

        return $this;
    }

    // ── Post identification ──

    /**
     * @param int|list<int> $id
     */
    public function id(int|array $id): self
    {
        if (\is_array($id)) {
            $this->args['post__in'] = $id;
        } else {
            $this->args['p'] = $id;
        }

        return $this;
    }

    /**
     * @param list<int> $ids
     */
    public function notIn(array $ids): self
    {
        $this->args['post__not_in'] = $ids;

        return $this;
    }

    public function parent(int $parentId): self
    {
        $this->args['post_parent'] = $parentId;

        return $this;
    }

    /**
     * @param list<int> $parentIds
     */
    public function parentIn(array $parentIds): self
    {
        $this->args['post_parent__in'] = $parentIds;

        return $this;
    }

    // ── Search ──

    public function search(string $keyword): self
    {
        $this->args['s'] = $keyword;

        return $this;
    }

    // ── Pagination ──

    public function limit(int $limit): self
    {
        $this->args['posts_per_page'] = $limit;

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

    // ── Ordering ──

    /**
     * @param string|array<string, string> $orderBy
     */
    public function orderBy(string|array $orderBy, Order|string $order = Order::Desc): self
    {
        $this->args['orderby'] = $orderBy;
        if (\is_string($orderBy)) {
            $this->args['order'] = $order instanceof Order ? $order->value : $order;
        }

        return $this;
    }

    public function orderByMeta(string $metaKey, Order|string $order = Order::Desc, MetaType|string|null $metaType = null): self
    {
        $this->args['meta_key'] = $metaKey;
        $this->args['orderby'] = 'meta_value';
        $this->args['order'] = $order instanceof Order ? $order->value : $order;

        if ($metaType !== null) {
            $type = $metaType instanceof MetaType ? $metaType->value : $metaType;
            if (\in_array($type, ['NUMERIC', 'DECIMAL', 'SIGNED', 'UNSIGNED'], true)) {
                $this->args['orderby'] = 'meta_value_num';
            }
            $this->args['meta_type'] = $type;
        }

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

        $metaQuery = $this->conditions->toMetaQuery($this->parameters);
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
}
