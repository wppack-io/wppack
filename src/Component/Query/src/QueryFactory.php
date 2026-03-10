<?php

declare(strict_types=1);

namespace WpPack\Component\Query;

use WpPack\Component\Query\Builder\PostQueryBuilder;
use WpPack\Component\Query\Builder\TermQueryBuilder;
use WpPack\Component\Query\Builder\UserQueryBuilder;

final class QueryFactory
{
    /**
     * @param string|list<string>|null $postType
     */
    public function posts(string|array|null $postType = null): PostQueryBuilder
    {
        $builder = new PostQueryBuilder();

        if ($postType !== null) {
            $builder->arg('post_type', $postType);
        }

        return $builder;
    }

    public function users(): UserQueryBuilder
    {
        return new UserQueryBuilder();
    }

    /**
     * @param string|list<string>|null $taxonomy
     */
    public function terms(string|array|null $taxonomy = null): TermQueryBuilder
    {
        $builder = new TermQueryBuilder();

        if ($taxonomy !== null) {
            $builder->arg('taxonomy', $taxonomy);
        }

        return $builder;
    }
}
