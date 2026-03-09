<?php

declare(strict_types=1);

namespace WpPack\Component\Query\Tests\Builder;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Query\Builder\PostQueryBuilder;
use WpPack\Component\Query\Condition\ConditionGroup;
use WpPack\Component\Query\Enum\MetaType;
use WpPack\Component\Query\Enum\Order;
use WpPack\Component\Query\Enum\PostStatus;

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

    // ── Meta conditions ──

    #[Test]
    public function whereAddsMetaCondition(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder
            ->where('m.featured = :feat')
            ->setParameter('feat', true)
            ->toArray();

        self::assertSame([
            'relation' => 'AND',
            ['key' => 'featured', 'value' => true, 'compare' => '='],
        ], $args['meta_query']);
    }

    #[Test]
    public function whereWithCompareOperator(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder
            ->where('m.price <= :price')
            ->setParameter('price', 100)
            ->toArray();

        self::assertSame([
            'relation' => 'AND',
            ['key' => 'price', 'value' => 100, 'compare' => '<='],
        ], $args['meta_query']);
    }

    #[Test]
    public function whereWithTypeHint(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder
            ->where('m.price:numeric <= :price')
            ->setParameter('price', 100)
            ->toArray();

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
            ->where('m.featured = :feat')
            ->andWhere('m.price:numeric <= :price')
            ->setParameter('feat', true)
            ->setParameter('price', 100)
            ->toArray();

        self::assertSame('AND', $args['meta_query']['relation']);
        self::assertCount(3, $args['meta_query']); // relation + 2 conditions
    }

    #[Test]
    public function orWhereConditions(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder
            ->orWhere('m.featured = :feat')
            ->orWhere('m.on_sale = :sale')
            ->setParameter('feat', true)
            ->setParameter('sale', true)
            ->toArray();

        self::assertSame('OR', $args['meta_query']['relation']);
        self::assertCount(3, $args['meta_query']); // relation + 2 conditions
    }

    #[Test]
    public function mixedAndOrWhereConditions(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder
            ->where('m.status = :status')
            ->orWhere('m.featured = :feat')
            ->setParameter('status', 'active')
            ->setParameter('feat', true)
            ->toArray();

        self::assertSame('AND', $args['meta_query']['relation']);
        self::assertArrayHasKey('relation', $args['meta_query']);
    }

    #[Test]
    public function nestedWhereWithClosure(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder
            ->where('m.status = :status')
            ->andWhere(function (ConditionGroup $group): void {
                $group->where('m.featured = :feat')
                    ->orWhere('m.on_sale = :sale');
            })
            ->setParameter('status', 'active')
            ->setParameter('feat', true)
            ->setParameter('sale', true)
            ->toArray();

        self::assertSame('AND', $args['meta_query']['relation']);
    }

    #[Test]
    public function whereExistsViaExpression(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder->where('m.thumbnail EXISTS')->toArray();

        self::assertSame([
            'relation' => 'AND',
            ['key' => 'thumbnail', 'value' => '', 'compare' => 'EXISTS'],
        ], $args['meta_query']);
    }

    #[Test]
    public function whereNotExistsViaExpression(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder->where('m.thumbnail NOT EXISTS')->toArray();

        self::assertSame([
            'relation' => 'AND',
            ['key' => 'thumbnail', 'value' => '', 'compare' => 'NOT EXISTS'],
        ], $args['meta_query']);
    }

    // ── Taxonomy via expression ──

    #[Test]
    public function taxonomyViaExpression(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder
            ->where('t.category IN :cats')
            ->setParameter('cats', [5, 10])
            ->toArray();

        self::assertCount(2, $args['tax_query']); // relation + 1 condition
        self::assertSame('category', $args['tax_query'][0]['taxonomy']);
        self::assertSame([5, 10], $args['tax_query'][0]['terms']);
        self::assertSame('term_id', $args['tax_query'][0]['field']);
    }

    #[Test]
    public function taxonomyWithSlugHint(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder
            ->where('t.category:slug IN :cats')
            ->setParameter('cats', ['electronics'])
            ->toArray();

        self::assertSame('slug', $args['tax_query'][0]['field']);
        self::assertSame('IN', $args['tax_query'][0]['operator']);
    }

    #[Test]
    public function multipleTaxonomyConditionsAnd(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder
            ->where('t.category IN :cats')
            ->andWhere('t.post_tag:slug IN :tags')
            ->setParameter('cats', [1])
            ->setParameter('tags', ['sale'])
            ->toArray();

        self::assertSame('AND', $args['tax_query']['relation']);
        self::assertSame('category', $args['tax_query'][0]['taxonomy']);
        self::assertSame('post_tag', $args['tax_query'][1]['taxonomy']);
    }

    #[Test]
    public function multipleTaxonomyConditionsOr(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder
            ->orWhere('t.category IN :cats')
            ->orWhere('t.post_tag:slug IN :tags')
            ->setParameter('cats', [1])
            ->setParameter('tags', ['sale'])
            ->toArray();

        self::assertSame('OR', $args['tax_query']['relation']);
    }

    #[Test]
    public function taxonomyExistsViaExpression(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder->where('t.category EXISTS')->toArray();

        self::assertSame([
            'relation' => 'AND',
            ['taxonomy' => 'category', 'operator' => 'EXISTS'],
        ], $args['tax_query']);
    }

    #[Test]
    public function taxonomyNotExistsViaExpression(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder->where('t.category NOT EXISTS')->toArray();

        self::assertSame([
            'relation' => 'AND',
            ['taxonomy' => 'category', 'operator' => 'NOT EXISTS'],
        ], $args['tax_query']);
    }

    // ── Mixed meta and tax ──

    #[Test]
    public function mixedMetaAndTaxConditions(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder
            ->where('m.featured = :feat')
            ->andWhere('t.category:slug IN :cats')
            ->setParameter('feat', true)
            ->setParameter('cats', ['electronics'])
            ->toArray();

        self::assertArrayHasKey('meta_query', $args);
        self::assertArrayHasKey('tax_query', $args);
        self::assertSame('featured', $args['meta_query'][0]['key']);
        self::assertSame('category', $args['tax_query'][0]['taxonomy']);
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
            ->status(PostStatus::Publish)
            ->where('m.featured = :feat')
            ->andWhere('m.price:numeric <= :price')
            ->andWhere('t.category:slug IN :cats')
            ->setParameter('feat', true)
            ->setParameter('price', 100)
            ->setParameter('cats', ['electronics'])
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

    // ── setParameter ──

    #[Test]
    public function setParameterOverwritesPreviousValue(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder
            ->where('m.price:numeric <= :price')
            ->setParameter('price', 100)
            ->setParameter('price', 200)
            ->toArray();

        self::assertSame(200, $args['meta_query'][0]['value']);
    }

    // ── WQL compound expressions ──

    #[Test]
    public function compoundAndExpression(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder
            ->where('m.featured = :feat AND m.on_sale = :sale')
            ->setParameter('feat', true)
            ->setParameter('sale', true)
            ->toArray();

        self::assertArrayHasKey('meta_query', $args);
        self::assertSame('AND', $args['meta_query']['relation']);
        // Compound expression becomes a nested AND group
        $nested = $args['meta_query'][0];
        self::assertSame('AND', $nested['relation']);
        self::assertSame('featured', $nested[0]['key']);
        self::assertSame('on_sale', $nested[1]['key']);
    }

    #[Test]
    public function compoundOrExpression(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder
            ->where('m.featured = :feat OR m.on_sale = :sale')
            ->setParameter('feat', true)
            ->setParameter('sale', true)
            ->toArray();

        self::assertArrayHasKey('meta_query', $args);
    }

    #[Test]
    public function compoundExpressionWithParentheses(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder
            ->where('(m.featured = :feat OR m.on_sale = :sale) AND m.status = :status')
            ->setParameter('feat', true)
            ->setParameter('sale', true)
            ->setParameter('status', 'active')
            ->toArray();

        self::assertArrayHasKey('meta_query', $args);
        self::assertSame('AND', $args['meta_query']['relation']);
    }

    #[Test]
    public function compoundExpressionWithMixedPrefixAnd(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder
            ->where('m.featured = :feat AND t.category IN :cats')
            ->setParameter('feat', true)
            ->setParameter('cats', [1, 2])
            ->toArray();

        self::assertArrayHasKey('meta_query', $args);
        self::assertArrayHasKey('tax_query', $args);
    }

    #[Test]
    public function compoundExpressionWithMixedPrefixOrThrows(): void
    {
        $builder = new PostQueryBuilder();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot mix prefixes');

        $builder->where('m.featured = :feat OR t.category IN :cats');
    }

    #[Test]
    public function compoundExpressionPrecedence(): void
    {
        // m.a OR m.b AND m.c → m.a OR (m.b AND m.c)
        $builder = new PostQueryBuilder();
        $args = $builder
            ->where('m.a = :a OR m.b = :b AND m.c = :c')
            ->setParameter('a', 1)
            ->setParameter('b', 2)
            ->setParameter('c', 3)
            ->toArray();

        self::assertArrayHasKey('meta_query', $args);
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
            ->status(PostStatus::Publish)
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
            ->status(PostStatus::Publish)
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
            ->status(PostStatus::Publish)
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
