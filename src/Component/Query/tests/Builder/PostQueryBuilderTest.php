<?php

declare(strict_types=1);

namespace WpPack\Component\Query\Tests\Builder;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Query\Builder\PostQueryBuilder;
use WpPack\Component\Query\Condition\MetaConditionGroup;
use WpPack\Component\Query\Enum\MetaCompare;
use WpPack\Component\Query\Enum\MetaType;
use WpPack\Component\Query\Enum\Order;
use WpPack\Component\Query\Enum\PostStatus;
use WpPack\Component\Query\Enum\TaxField;
use WpPack\Component\Query\Enum\TaxOperator;

final class PostQueryBuilderTest extends TestCase
{
    #[Test]
    public function typeSetsSinglePostType(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder->type('product')->toArray();

        self::assertSame('product', $args['post_type']);
    }

    #[Test]
    public function typeOverwritesPreviousValue(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder->type('post')->type('product')->toArray();

        self::assertSame('product', $args['post_type']);
    }

    #[Test]
    public function typeSetsArrayOfPostTypes(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder->type(['post', 'page'])->toArray();

        self::assertSame(['post', 'page'], $args['post_type']);
    }

    #[Test]
    public function statusSetsWithEnum(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder->status(PostStatus::Publish)->toArray();

        self::assertSame('publish', $args['post_status']);
    }

    #[Test]
    public function statusSetsWithString(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder->status('draft')->toArray();

        self::assertSame('draft', $args['post_status']);
    }

    #[Test]
    public function statusSetsArrayOfStatuses(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder->status([PostStatus::Publish, PostStatus::Draft])->toArray();

        self::assertSame(['publish', 'draft'], $args['post_status']);
    }

    #[Test]
    public function publishedShorthand(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder->published()->toArray();

        self::assertSame('publish', $args['post_status']);
    }

    #[Test]
    public function draftShorthand(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder->draft()->toArray();

        self::assertSame('draft', $args['post_status']);
    }

    // ── Meta conditions ──

    #[Test]
    public function whereAddsMetaCondition(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder->where('featured', true)->toArray();

        self::assertSame([
            'relation' => 'AND',
            ['key' => 'featured', 'value' => true, 'compare' => '='],
        ], $args['meta_query']);
    }

    #[Test]
    public function whereWithCompareEnum(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder->where('price', 100, MetaCompare::LessThanOrEqual)->toArray();

        self::assertSame([
            'relation' => 'AND',
            ['key' => 'price', 'value' => 100, 'compare' => '<='],
        ], $args['meta_query']);
    }

    #[Test]
    public function whereWithCompareString(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder->where('price', 100, '<=')->toArray();

        self::assertSame([
            'relation' => 'AND',
            ['key' => 'price', 'value' => 100, 'compare' => '<='],
        ], $args['meta_query']);
    }

    #[Test]
    public function whereWithTypeEnum(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder->where('price', 100, '<=', MetaType::Numeric)->toArray();

        self::assertSame([
            'relation' => 'AND',
            ['key' => 'price', 'value' => 100, 'compare' => '<=', 'type' => 'NUMERIC'],
        ], $args['meta_query']);
    }

    #[Test]
    public function multipleAndWhereConditions(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder
            ->where('featured', true)
            ->andWhere('price', 100, '<=')
            ->toArray();

        self::assertSame('AND', $args['meta_query']['relation']);
        self::assertCount(3, $args['meta_query']); // relation + 2 conditions
    }

    #[Test]
    public function orWhereConditions(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder
            ->orWhere('featured', true)
            ->orWhere('on_sale', true)
            ->toArray();

        self::assertSame('OR', $args['meta_query']['relation']);
        self::assertCount(3, $args['meta_query']); // relation + 2 conditions
    }

    #[Test]
    public function mixedAndOrWhereConditions(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder
            ->where('status', 'active')
            ->orWhere('featured', true)
            ->toArray();

        self::assertSame('AND', $args['meta_query']['relation']);
        // Should have AND clause + nested OR group
        self::assertArrayHasKey('relation', $args['meta_query']);
    }

    #[Test]
    public function nestedWhereWithClosure(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder
            ->where('status', 'active')
            ->andWhere(function (MetaConditionGroup $group): void {
                $group->where('featured', true)
                    ->orWhere('on_sale', true);
            })
            ->toArray();

        self::assertSame('AND', $args['meta_query']['relation']);
    }

    #[Test]
    public function whereExistsAddsExistsCondition(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder->whereExists('thumbnail')->toArray();

        self::assertSame([
            'relation' => 'AND',
            ['key' => 'thumbnail', 'value' => '', 'compare' => 'EXISTS'],
        ], $args['meta_query']);
    }

    #[Test]
    public function whereNotExistsAddsNotExistsCondition(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder->whereNotExists('thumbnail')->toArray();

        self::assertSame([
            'relation' => 'AND',
            ['key' => 'thumbnail', 'value' => '', 'compare' => 'NOT EXISTS'],
        ], $args['meta_query']);
    }

    // ── Taxonomy ──

    #[Test]
    public function taxonomyAddsTaxQuery(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder->taxonomy('category', [5, 10])->toArray();

        self::assertCount(1, $args['tax_query']);
        self::assertSame('category', $args['tax_query'][0]['taxonomy']);
        self::assertSame([5, 10], $args['tax_query'][0]['terms']);
    }

    #[Test]
    public function taxonomyWithCustomFieldAndOperator(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder->taxonomy('category', ['electronics'], TaxField::Slug, TaxOperator::In)->toArray();

        self::assertSame('slug', $args['tax_query'][0]['field']);
        self::assertSame('IN', $args['tax_query'][0]['operator']);
    }

    #[Test]
    public function multipleTaxonomyQueriesAccumulate(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder
            ->taxonomy('category', ['electronics'])
            ->taxonomy('tag', ['sale'])
            ->toArray();

        // 2 tax conditions + 'relation' key
        self::assertSame('AND', $args['tax_query']['relation']);
        self::assertSame('category', $args['tax_query'][0]['taxonomy']);
        self::assertSame('tag', $args['tax_query'][1]['taxonomy']);
    }

    #[Test]
    public function taxRelationSetsOrRelation(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder
            ->taxonomy('category', ['electronics'])
            ->taxonomy('tag', ['sale'])
            ->taxRelation('OR')
            ->toArray();

        self::assertSame('OR', $args['tax_query']['relation']);
    }

    // ── Author ──

    #[Test]
    public function authorSetsSingleAuthor(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder->author(5)->toArray();

        self::assertSame(5, $args['author']);
    }

    #[Test]
    public function authorSetsArrayOfAuthors(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder->author([5, 10])->toArray();

        self::assertSame([5, 10], $args['author__in']);
    }

    #[Test]
    public function authorNotInSetsExclusion(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder->authorNotIn([3, 7])->toArray();

        self::assertSame([3, 7], $args['author__not_in']);
    }

    // ── Post identification ──

    #[Test]
    public function idSetsSinglePostId(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder->id(42)->toArray();

        self::assertSame(42, $args['p']);
    }

    #[Test]
    public function idSetsArrayOfPostIds(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder->id([1, 2, 3])->toArray();

        self::assertSame([1, 2, 3], $args['post__in']);
    }

    #[Test]
    public function notInSetsExcludedPostIds(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder->notIn([99])->toArray();

        self::assertSame([99], $args['post__not_in']);
    }

    #[Test]
    public function parentSetsPostParent(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder->parent(10)->toArray();

        self::assertSame(10, $args['post_parent']);
    }

    #[Test]
    public function parentInSetsPostParentIn(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder->parentIn([10, 20])->toArray();

        self::assertSame([10, 20], $args['post_parent__in']);
    }

    // ── Search ──

    #[Test]
    public function searchSetsKeyword(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder->search('keyword')->toArray();

        self::assertSame('keyword', $args['s']);
    }

    // ── Pagination ──

    #[Test]
    public function limitSetsPostsPerPage(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder->limit(10)->toArray();

        self::assertSame(10, $args['posts_per_page']);
    }

    #[Test]
    public function pageSetsPaged(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder->page(2)->toArray();

        self::assertSame(2, $args['paged']);
    }

    #[Test]
    public function offsetSetsOffset(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder->offset(5)->toArray();

        self::assertSame(5, $args['offset']);
    }

    // ── Ordering ──

    #[Test]
    public function orderBySetsOrderByAndOrder(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder->orderBy('date', Order::Desc)->toArray();

        self::assertSame('date', $args['orderby']);
        self::assertSame('DESC', $args['order']);
    }

    #[Test]
    public function orderByDefaultsToDesc(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder->orderBy('title')->toArray();

        self::assertSame('title', $args['orderby']);
        self::assertSame('DESC', $args['order']);
    }

    #[Test]
    public function orderByWithStringOrder(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder->orderBy('date', 'ASC')->toArray();

        self::assertSame('ASC', $args['order']);
    }

    #[Test]
    public function orderByMetaSetsMetaKeyAndOrderBy(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder->orderByMeta('price', Order::Asc)->toArray();

        self::assertSame('price', $args['meta_key']);
        self::assertSame('meta_value', $args['orderby']);
        self::assertSame('ASC', $args['order']);
    }

    #[Test]
    public function orderByMetaWithNumericType(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder->orderByMeta('price', Order::Asc, MetaType::Numeric)->toArray();

        self::assertSame('meta_value_num', $args['orderby']);
        self::assertSame('NUMERIC', $args['meta_type']);
    }

    // ── Date ──

    #[Test]
    public function afterAddsDateQuery(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder->after('2024-01-01')->toArray();

        self::assertSame([['after' => '2024-01-01']], $args['date_query']);
    }

    #[Test]
    public function beforeAddsDateQuery(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder->before('2024-12-31')->toArray();

        self::assertSame([['before' => '2024-12-31']], $args['date_query']);
    }

    #[Test]
    public function afterAndBeforeCombine(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder->after('2024-01-01')->before('2024-12-31')->toArray();

        self::assertCount(2, $args['date_query']);
    }

    #[Test]
    public function dateAddsCustomDateQuery(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder->date(['year' => 2024, 'month' => 6])->toArray();

        self::assertSame([['year' => 2024, 'month' => 6]], $args['date_query']);
    }

    // ── Performance ──

    #[Test]
    public function noMetaCacheDisablesMetaCache(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder->noMetaCache()->toArray();

        self::assertFalse($args['update_post_meta_cache']);
    }

    #[Test]
    public function noTermCacheDisablesTermCache(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder->noTermCache()->toArray();

        self::assertFalse($args['update_post_term_cache']);
    }

    #[Test]
    public function withoutCountSetsNoFoundRows(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder->withoutCount()->toArray();

        self::assertTrue($args['no_found_rows']);
    }

    // ── Escape hatch ──

    #[Test]
    public function argSetsArbitraryArgument(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder->arg('custom_key', 'custom_value')->toArray();

        self::assertSame('custom_value', $args['custom_key']);
    }

    // ── Complex queries ──

    #[Test]
    public function complexQueryBuildsCorrectly(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder
            ->type('product')
            ->published()
            ->where('featured', true)
            ->andWhere('price', 100, '<=')
            ->taxonomy('category', ['electronics'], TaxField::Slug)
            ->author(5)
            ->limit(10)
            ->page(2)
            ->orderBy('date', Order::Desc)
            ->noMetaCache()
            ->toArray();

        self::assertSame('product', $args['post_type']);
        self::assertSame('publish', $args['post_status']);
        self::assertArrayHasKey('meta_query', $args);
        self::assertArrayHasKey('tax_query', $args);
        self::assertSame(5, $args['author']);
        self::assertSame(10, $args['posts_per_page']);
        self::assertSame(2, $args['paged']);
        self::assertSame('date', $args['orderby']);
        self::assertSame('DESC', $args['order']);
        self::assertFalse($args['update_post_meta_cache']);
    }

    #[Test]
    public function emptyBuilderReturnsEmptyArgs(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder->toArray();

        self::assertSame([], $args);
    }

    #[Test]
    public function noMetaQueryWhenNoConditions(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder->type('post')->toArray();

        self::assertArrayNotHasKey('meta_query', $args);
    }

    #[Test]
    public function noTaxQueryWhenNoConditions(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder->type('post')->toArray();

        self::assertArrayNotHasKey('tax_query', $args);
    }

    #[Test]
    public function noDateQueryWhenNoConditions(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder->type('post')->toArray();

        self::assertArrayNotHasKey('date_query', $args);
    }

    // ── Execution methods (WordPress integration) ──

    #[Test]
    public function getReturnsPostQueryResult(): void
    {
        if (!class_exists(\WP_Query::class)) {
            self::markTestSkipped('WordPress is not available.');
        }

        $result = (new PostQueryBuilder())
            ->type('post')
            ->published()
            ->limit(5)
            ->get();

        self::assertInstanceOf(\WpPack\Component\Query\Result\PostQueryResult::class, $result);
    }

    #[Test]
    public function firstReturnsNullableWpPost(): void
    {
        if (!class_exists(\WP_Query::class)) {
            self::markTestSkipped('WordPress is not available.');
        }

        $post = (new PostQueryBuilder())
            ->type('post')
            ->id(999999)
            ->first();

        self::assertNull($post);
    }

    #[Test]
    public function getIdsReturnsList(): void
    {
        if (!class_exists(\WP_Query::class)) {
            self::markTestSkipped('WordPress is not available.');
        }

        $ids = (new PostQueryBuilder())
            ->type('post')
            ->published()
            ->limit(5)
            ->getIds();

        self::assertIsArray($ids);
    }

    #[Test]
    public function countReturnsInteger(): void
    {
        if (!class_exists(\WP_Query::class)) {
            self::markTestSkipped('WordPress is not available.');
        }

        $count = (new PostQueryBuilder())
            ->type('post')
            ->published()
            ->count();

        self::assertIsInt($count);
    }

    #[Test]
    public function existsReturnsBool(): void
    {
        if (!class_exists(\WP_Query::class)) {
            self::markTestSkipped('WordPress is not available.');
        }

        $exists = (new PostQueryBuilder())
            ->type('post')
            ->id(999999)
            ->exists();

        self::assertFalse($exists);
    }
}
