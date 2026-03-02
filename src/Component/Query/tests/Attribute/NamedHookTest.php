<?php

declare(strict_types=1);

namespace WpPack\Component\Query\Tests\Attribute;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Hook\Attribute\Action;
use WpPack\Component\Hook\Attribute\Filter;
use WpPack\Component\Hook\Hook;
use WpPack\Component\Hook\HookType;
use WpPack\Component\Query\Attribute\Action\ParseQueryAction;
use WpPack\Component\Query\Attribute\Action\ParseRequestAction;
use WpPack\Component\Query\Attribute\Action\PreGetPostsAction;
use WpPack\Component\Query\Attribute\Filter\FoundPostsFilter;
use WpPack\Component\Query\Attribute\Filter\FoundPostsQueryFilter;
use WpPack\Component\Query\Attribute\Filter\PostsCacheResultsFilter;
use WpPack\Component\Query\Attribute\Filter\PostsClausesFilter;
use WpPack\Component\Query\Attribute\Filter\PostsDistinctFilter;
use WpPack\Component\Query\Attribute\Filter\PostsFieldsFilter;
use WpPack\Component\Query\Attribute\Filter\PostsGroupbyFilter;
use WpPack\Component\Query\Attribute\Filter\PostsJoinFilter;
use WpPack\Component\Query\Attribute\Filter\PostsOrderbyFilter;
use WpPack\Component\Query\Attribute\Filter\PostsRequestFilter;
use WpPack\Component\Query\Attribute\Filter\PostsResultsFilter;
use WpPack\Component\Query\Attribute\Filter\PostsSearchColumnsFilter;
use WpPack\Component\Query\Attribute\Filter\PostsSearchFilter;
use WpPack\Component\Query\Attribute\Filter\PostsSearchOrderbyFilter;
use WpPack\Component\Query\Attribute\Filter\PostsWhereFilter;
use WpPack\Component\Query\Attribute\Filter\PostsWherePagedFilter;
use WpPack\Component\Query\Attribute\Filter\QueryVarsFilter;
use WpPack\Component\Query\Attribute\Filter\ThePostsFilter;
use WpPack\Component\Query\Attribute\Filter\UpdatePostMetaCacheFilter;
use WpPack\Component\Query\Attribute\Filter\UpdatePostTermCacheFilter;

final class NamedHookTest extends TestCase
{
    #[Test]
    public function parseQueryActionHasCorrectHookName(): void
    {
        $action = new ParseQueryAction();

        self::assertSame('parse_query', $action->hook);
        self::assertSame(HookType::Action, $action->type);
        self::assertSame(10, $action->priority);
    }

    #[Test]
    public function parseRequestActionHasCorrectHookName(): void
    {
        $action = new ParseRequestAction();

        self::assertSame('parse_request', $action->hook);
    }

    #[Test]
    public function preGetPostsActionHasCorrectHookName(): void
    {
        $action = new PreGetPostsAction();

        self::assertSame('pre_get_posts', $action->hook);
    }

    #[Test]
    public function preGetPostsActionAcceptsCustomPriority(): void
    {
        $action = new PreGetPostsAction(priority: 5);

        self::assertSame(5, $action->priority);
    }

    #[Test]
    public function foundPostsFilterHasCorrectHookName(): void
    {
        $filter = new FoundPostsFilter();

        self::assertSame('found_posts', $filter->hook);
        self::assertSame(HookType::Filter, $filter->type);
        self::assertSame(10, $filter->priority);
    }

    #[Test]
    public function foundPostsQueryFilterHasCorrectHookName(): void
    {
        $filter = new FoundPostsQueryFilter();

        self::assertSame('found_posts_query', $filter->hook);
    }

    #[Test]
    public function postsCacheResultsFilterHasCorrectHookName(): void
    {
        $filter = new PostsCacheResultsFilter();

        self::assertSame('posts_cache_results', $filter->hook);
    }

    #[Test]
    public function postsClausesFilterHasCorrectHookName(): void
    {
        $filter = new PostsClausesFilter();

        self::assertSame('posts_clauses', $filter->hook);
    }

    #[Test]
    public function postsDistinctFilterHasCorrectHookName(): void
    {
        $filter = new PostsDistinctFilter();

        self::assertSame('posts_distinct', $filter->hook);
    }

    #[Test]
    public function postsFieldsFilterHasCorrectHookName(): void
    {
        $filter = new PostsFieldsFilter();

        self::assertSame('posts_fields', $filter->hook);
    }

    #[Test]
    public function postsGroupbyFilterHasCorrectHookName(): void
    {
        $filter = new PostsGroupbyFilter();

        self::assertSame('posts_groupby', $filter->hook);
    }

    #[Test]
    public function postsJoinFilterHasCorrectHookName(): void
    {
        $filter = new PostsJoinFilter();

        self::assertSame('posts_join', $filter->hook);
    }

    #[Test]
    public function postsOrderbyFilterHasCorrectHookName(): void
    {
        $filter = new PostsOrderbyFilter();

        self::assertSame('posts_orderby', $filter->hook);
    }

    #[Test]
    public function postsRequestFilterHasCorrectHookName(): void
    {
        $filter = new PostsRequestFilter();

        self::assertSame('posts_request', $filter->hook);
    }

    #[Test]
    public function postsResultsFilterHasCorrectHookName(): void
    {
        $filter = new PostsResultsFilter();

        self::assertSame('posts_results', $filter->hook);
    }

    #[Test]
    public function postsSearchColumnsFilterHasCorrectHookName(): void
    {
        $filter = new PostsSearchColumnsFilter();

        self::assertSame('posts_search_columns', $filter->hook);
    }

    #[Test]
    public function postsSearchFilterHasCorrectHookName(): void
    {
        $filter = new PostsSearchFilter();

        self::assertSame('posts_search', $filter->hook);
    }

    #[Test]
    public function postsSearchOrderbyFilterHasCorrectHookName(): void
    {
        $filter = new PostsSearchOrderbyFilter();

        self::assertSame('posts_search_orderby', $filter->hook);
    }

    #[Test]
    public function postsWhereFilterHasCorrectHookName(): void
    {
        $filter = new PostsWhereFilter();

        self::assertSame('posts_where', $filter->hook);
    }

    #[Test]
    public function postsWherePagedFilterHasCorrectHookName(): void
    {
        $filter = new PostsWherePagedFilter();

        self::assertSame('posts_where_paged', $filter->hook);
    }

    #[Test]
    public function queryVarsFilterHasCorrectHookName(): void
    {
        $filter = new QueryVarsFilter();

        self::assertSame('query_vars', $filter->hook);
    }

    #[Test]
    public function thePostsFilterHasCorrectHookName(): void
    {
        $filter = new ThePostsFilter();

        self::assertSame('the_posts', $filter->hook);
    }

    #[Test]
    public function updatePostMetaCacheFilterHasCorrectHookName(): void
    {
        $filter = new UpdatePostMetaCacheFilter();

        self::assertSame('update_post_meta_cache', $filter->hook);
    }

    #[Test]
    public function updatePostTermCacheFilterHasCorrectHookName(): void
    {
        $filter = new UpdatePostTermCacheFilter();

        self::assertSame('update_post_term_cache', $filter->hook);
    }

    #[Test]
    public function allActionsExtendAction(): void
    {
        self::assertInstanceOf(Action::class, new ParseQueryAction());
        self::assertInstanceOf(Action::class, new ParseRequestAction());
        self::assertInstanceOf(Action::class, new PreGetPostsAction());
    }

    #[Test]
    public function allFiltersExtendFilter(): void
    {
        self::assertInstanceOf(Filter::class, new FoundPostsFilter());
        self::assertInstanceOf(Filter::class, new FoundPostsQueryFilter());
        self::assertInstanceOf(Filter::class, new PostsCacheResultsFilter());
        self::assertInstanceOf(Filter::class, new PostsClausesFilter());
        self::assertInstanceOf(Filter::class, new PostsDistinctFilter());
        self::assertInstanceOf(Filter::class, new PostsFieldsFilter());
        self::assertInstanceOf(Filter::class, new PostsGroupbyFilter());
        self::assertInstanceOf(Filter::class, new PostsJoinFilter());
        self::assertInstanceOf(Filter::class, new PostsOrderbyFilter());
        self::assertInstanceOf(Filter::class, new PostsRequestFilter());
        self::assertInstanceOf(Filter::class, new PostsResultsFilter());
        self::assertInstanceOf(Filter::class, new PostsSearchColumnsFilter());
        self::assertInstanceOf(Filter::class, new PostsSearchFilter());
        self::assertInstanceOf(Filter::class, new PostsSearchOrderbyFilter());
        self::assertInstanceOf(Filter::class, new PostsWhereFilter());
        self::assertInstanceOf(Filter::class, new PostsWherePagedFilter());
        self::assertInstanceOf(Filter::class, new QueryVarsFilter());
        self::assertInstanceOf(Filter::class, new ThePostsFilter());
        self::assertInstanceOf(Filter::class, new UpdatePostMetaCacheFilter());
        self::assertInstanceOf(Filter::class, new UpdatePostTermCacheFilter());
    }

    #[Test]
    public function namedHooksAreDetectedByIsInstanceof(): void
    {
        $class = new class {
            #[PreGetPostsAction]
            public function onPreGetPosts(): void {}

            #[PostsWhereFilter(priority: 5)]
            public function onPostsWhere(): void {}
        };

        $actionMethod = new \ReflectionMethod($class, 'onPreGetPosts');
        $attributes = $actionMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('pre_get_posts', $attributes[0]->newInstance()->hook);

        $filterMethod = new \ReflectionMethod($class, 'onPostsWhere');
        $attributes = $filterMethod->getAttributes(Hook::class, \ReflectionAttribute::IS_INSTANCEOF);
        self::assertCount(1, $attributes);
        self::assertSame('posts_where', $attributes[0]->newInstance()->hook);
        self::assertSame(5, $attributes[0]->newInstance()->priority);
    }
}
