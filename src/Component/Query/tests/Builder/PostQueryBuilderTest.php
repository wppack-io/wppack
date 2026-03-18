<?php

declare(strict_types=1);

namespace WpPack\Component\Query\Tests\Builder;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Query\Builder\PostQueryBuilder;
use WpPack\Component\Query\Condition\ConditionGroup;
use WpPack\Component\Query\Enum\Order;
use WpPack\Component\Query\Enum\PostStatus;

final class PostQueryBuilderTest extends TestCase
{
    // ── Standard field conditions via where() ──

    #[Test]
    public function wherePostTypeEquals(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder
            ->where('p.type = :type')
            ->setParameter('type', 'product')
            ->toArray();

        self::assertSame('product', $args['post_type']);
    }

    #[Test]
    public function wherePostTypeIn(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder
            ->where('p.type IN :types')
            ->setParameter('types', ['post', 'page'])
            ->toArray();

        self::assertSame(['post', 'page'], $args['post_type']);
    }

    #[Test]
    public function wherePostTypeWithLongPrefix(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder
            ->where('post.type = :type')
            ->setParameter('type', 'product')
            ->toArray();

        self::assertSame('product', $args['post_type']);
    }

    #[Test]
    public function wherePostStatusEquals(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder
            ->where('p.status = :status')
            ->setParameter('status', 'publish')
            ->toArray();

        self::assertSame('publish', $args['post_status']);
    }

    #[Test]
    public function wherePostStatusIn(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder
            ->where('p.status IN :statuses')
            ->setParameter('statuses', ['publish', 'draft'])
            ->toArray();

        self::assertSame(['publish', 'draft'], $args['post_status']);
    }

    #[Test]
    public function whereAuthorEquals(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder
            ->where('p.author = :author')
            ->setParameter('author', 5)
            ->toArray();

        self::assertSame(5, $args['author']);
    }

    #[Test]
    public function whereAuthorIn(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder
            ->where('p.author IN :authors')
            ->setParameter('authors', [5, 10])
            ->toArray();

        self::assertSame([5, 10], $args['author__in']);
    }

    #[Test]
    public function whereAuthorNotIn(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder
            ->where('p.author NOT IN :authors')
            ->setParameter('authors', [3, 7])
            ->toArray();

        self::assertSame([3, 7], $args['author__not_in']);
    }

    #[Test]
    public function whereIdEquals(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder
            ->where('p.id = :id')
            ->setParameter('id', 42)
            ->toArray();

        self::assertSame(42, $args['p']);
    }

    #[Test]
    public function whereIdIn(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder
            ->where('p.id IN :ids')
            ->setParameter('ids', [1, 2, 3])
            ->toArray();

        self::assertSame([1, 2, 3], $args['post__in']);
    }

    #[Test]
    public function whereIdNotIn(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder
            ->where('p.id NOT IN :ids')
            ->setParameter('ids', [99])
            ->toArray();

        self::assertSame([99], $args['post__not_in']);
    }

    #[Test]
    public function whereParentEquals(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder
            ->where('p.parent = :parent')
            ->setParameter('parent', 10)
            ->toArray();

        self::assertSame(10, $args['post_parent']);
    }

    #[Test]
    public function whereParentIn(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder
            ->where('p.parent IN :parents')
            ->setParameter('parents', [10, 20])
            ->toArray();

        self::assertSame([10, 20], $args['post_parent__in']);
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

    // ── Mixed standard fields and meta/tax ──

    #[Test]
    public function standardFieldsAndMetaConditions(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder
            ->where('p.type = :type')
            ->andWhere('p.status = :status')
            ->andWhere('m.featured = :feat')
            ->andWhere('t.category:slug IN :cats')
            ->setParameter('type', 'product')
            ->setParameter('status', 'publish')
            ->setParameter('feat', true)
            ->setParameter('cats', ['electronics'])
            ->toArray();

        self::assertSame('product', $args['post_type']);
        self::assertSame('publish', $args['post_status']);
        self::assertArrayHasKey('meta_query', $args);
        self::assertArrayHasKey('tax_query', $args);
    }

    // ── setParameters (batch) ──

    #[Test]
    public function setParametersBatch(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder
            ->where('p.type = :type')
            ->andWhere('p.status = :status')
            ->setParameters(['type' => 'product', 'status' => 'publish'])
            ->toArray();

        self::assertSame('product', $args['post_type']);
        self::assertSame('publish', $args['post_status']);
    }

    // ── Search ──

    #[Test]
    public function searchSetsKeyword(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder->search('keyword')->toArray();

        self::assertSame('keyword', $args['s']);
    }

    // ── Pagination (Doctrine naming) ──

    #[Test]
    public function setMaxResultsSetsPostsPerPage(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder->setMaxResults(10)->toArray();

        self::assertSame(10, $args['posts_per_page']);
    }

    #[Test]
    public function setFirstResultSetsOffset(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder->setFirstResult(5)->toArray();

        self::assertSame(5, $args['offset']);
    }

    #[Test]
    public function setMaxResultsAndSetFirstResultCombine(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder->setMaxResults(10)->setFirstResult(20)->toArray();

        self::assertSame(10, $args['posts_per_page']);
        self::assertSame(20, $args['offset']);
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

    // ── WQL ORDER BY ──

    #[Test]
    public function orderByWqlSingleField(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder->orderBy('p.date', Order::Desc)->toArray();

        self::assertSame('date', $args['orderby']);
        self::assertSame('DESC', $args['order']);
    }

    #[Test]
    public function orderByWqlSingleFieldWithoutPrefix(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder->orderBy('date', Order::Desc)->toArray();

        self::assertSame('date', $args['orderby']);
        self::assertSame('DESC', $args['order']);
    }

    #[Test]
    public function orderByWqlMeta(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder->orderBy('m.price:numeric', Order::Desc)->toArray();

        self::assertSame('price', $args['meta_key']);
        self::assertSame('meta_value_num', $args['orderby']);
        self::assertSame('NUMERIC', $args['meta_type']);
        self::assertSame('DESC', $args['order']);
    }

    #[Test]
    public function orderByWqlMultiple(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder
            ->orderBy('p.date', Order::Desc)
            ->addOrderBy('p.title', Order::Asc)
            ->toArray();

        self::assertSame(['date' => 'DESC', 'title' => 'ASC'], $args['orderby']);
    }

    #[Test]
    public function orderByWqlMixedMetaAndStandard(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder
            ->orderBy('m.price:numeric', Order::Desc)
            ->addOrderBy('p.date', Order::Asc)
            ->toArray();

        self::assertSame([
            '__wppack_ob_price' => 'DESC',
            'date' => 'ASC',
        ], $args['orderby']);

        self::assertArrayHasKey('meta_query', $args);
        self::assertArrayHasKey('__wppack_ob_price', $args['meta_query']);
    }

    #[Test]
    public function addOrderByAppends(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder
            ->orderBy('p.date', Order::Desc)
            ->addOrderBy('p.title', Order::Asc)
            ->toArray();

        self::assertSame(['date' => 'DESC', 'title' => 'ASC'], $args['orderby']);
    }

    #[Test]
    public function orderByReplacesAdd(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder
            ->addOrderBy('p.title', Order::Asc)
            ->orderBy('p.date', Order::Desc)
            ->toArray();

        self::assertSame('date', $args['orderby']);
        self::assertSame('DESC', $args['order']);
    }

    #[Test]
    public function orderByWqlWithWhereMetaQuery(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder
            ->where('m.featured = :feat')
            ->setParameter('feat', true)
            ->orderBy('m.price:numeric', Order::Desc)
            ->addOrderBy('p.date', Order::Asc)
            ->toArray();

        // orderby uses array format with named clause
        self::assertSame([
            '__wppack_ob_price' => 'DESC',
            'date' => 'ASC',
        ], $args['orderby']);

        // meta_query contains both WHERE condition and ORDER BY named clause
        self::assertArrayHasKey('meta_query', $args);
        self::assertArrayHasKey('__wppack_ob_price', $args['meta_query']);
    }

    #[Test]
    public function orderByBackwardCompatible(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder->orderBy('date', Order::Desc)->toArray();

        self::assertSame('date', $args['orderby']);
        self::assertSame('DESC', $args['order']);
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
            ->where('p.type = :type')
            ->andWhere('p.status = :status')
            ->andWhere('m.featured = :feat')
            ->andWhere('m.price:numeric <= :price')
            ->andWhere('t.category:slug IN :cats')
            ->andWhere('p.author = :author')
            ->setParameters([
                'type' => 'product',
                'status' => 'publish',
                'feat' => true,
                'price' => 100,
                'cats' => ['electronics'],
                'author' => 5,
            ])
            ->setMaxResults(10)
            ->setFirstResult(10)
            ->orderBy('date', Order::Desc)
            ->noMetaCache()
            ->toArray();

        self::assertSame('product', $args['post_type']);
        self::assertSame('publish', $args['post_status']);
        self::assertSame(5, $args['author']);
        self::assertArrayHasKey('meta_query', $args);
        self::assertArrayHasKey('tax_query', $args);
        self::assertSame(10, $args['posts_per_page']);
        self::assertSame(10, $args['offset']);
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
        $args = $builder
            ->where('p.type = :type')
            ->setParameter('type', 'post')
            ->toArray();

        self::assertArrayNotHasKey('meta_query', $args);
    }

    #[Test]
    public function noTaxQueryWhenNoConditions(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder
            ->where('p.type = :type')
            ->setParameter('type', 'post')
            ->toArray();

        self::assertArrayNotHasKey('tax_query', $args);
    }

    #[Test]
    public function noDateQueryWhenNoConditions(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder
            ->where('p.type = :type')
            ->setParameter('type', 'post')
            ->toArray();

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

    // ── Standard field error cases ──

    #[Test]
    public function orWhereWithStandardFieldThrows(): void
    {
        $builder = new PostQueryBuilder();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot be used in orWhere');

        $builder->orWhere('p.status = :status');
    }

    #[Test]
    public function compoundOrWithStandardFieldThrows(): void
    {
        $builder = new PostQueryBuilder();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot be used in OR expressions');

        $builder->where('p.status = :s OR p.type = :t');
    }

    #[Test]
    public function unknownPostFieldThrows(): void
    {
        $builder = new PostQueryBuilder();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown post field "unknown"');

        $builder
            ->where('p.unknown = :val')
            ->setParameter('val', 'test')
            ->toArray();
    }

    #[Test]
    public function unsupportedOperatorForPostStatusThrows(): void
    {
        $builder = new PostQueryBuilder();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported operator "LIKE" for field "post.status"');

        $builder
            ->where('p.status LIKE :val')
            ->setParameter('val', 'pub%')
            ->toArray();
    }

    #[Test]
    public function unsupportedOperatorForPostTypeThrows(): void
    {
        $builder = new PostQueryBuilder();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported operator ">" for field "post.type"');

        $builder
            ->where('p.type > :val')
            ->setParameter('val', 'post')
            ->toArray();
    }

    #[Test]
    public function unsupportedOperatorForAuthorThrows(): void
    {
        $builder = new PostQueryBuilder();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported operator "LIKE" for field "post.author"');

        $builder
            ->where('p.author LIKE :val')
            ->setParameter('val', '5%')
            ->toArray();
    }

    #[Test]
    public function unsupportedOperatorForIdThrows(): void
    {
        $builder = new PostQueryBuilder();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported operator "LIKE" for field "post.id"');

        $builder
            ->where('p.id LIKE :val')
            ->setParameter('val', '42%')
            ->toArray();
    }

    #[Test]
    public function unsupportedOperatorForParentThrows(): void
    {
        $builder = new PostQueryBuilder();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported operator "NOT IN" for field "post.parent"');

        $builder
            ->where('p.parent NOT IN :vals')
            ->setParameter('vals', [10, 20])
            ->toArray();
    }

    // ── orderBy with array argument ──

    #[Test]
    public function orderByWithArrayArgument(): void
    {
        $builder = new PostQueryBuilder();
        $args = $builder->orderBy(['date' => 'DESC', 'title' => 'ASC'])->toArray();

        self::assertSame(['date' => 'DESC', 'title' => 'ASC'], $args['orderby']);
    }

    #[Test]
    public function userPrefixNotAllowedInPostBuilder(): void
    {
        $builder = new PostQueryBuilder();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown prefix "u"');

        $builder->where('u.role = :role');
    }

    // ── Execution methods (WordPress integration) ──

    #[Test]
    public function getReturnsPostQueryResult(): void
    {
        $result = (new PostQueryBuilder())
            ->where('p.type = :type')
            ->andWhere('p.status = :status')
            ->setParameters(['type' => 'post', 'status' => 'publish'])
            ->setMaxResults(5)
            ->get();

        self::assertInstanceOf(\WpPack\Component\Query\Result\PostQueryResult::class, $result);
    }

    #[Test]
    public function firstReturnsNullableWpPost(): void
    {
        $post = (new PostQueryBuilder())
            ->where('p.type = :type')
            ->andWhere('p.id = :id')
            ->setParameters(['type' => 'post', 'id' => 999999])
            ->first();

        self::assertNull($post);
    }

    #[Test]
    public function getIdsReturnsList(): void
    {
        $ids = (new PostQueryBuilder())
            ->where('p.type = :type')
            ->andWhere('p.status = :status')
            ->setParameters(['type' => 'post', 'status' => 'publish'])
            ->setMaxResults(5)
            ->getIds();

        self::assertIsArray($ids);
    }

    #[Test]
    public function countReturnsInteger(): void
    {
        $count = (new PostQueryBuilder())
            ->where('p.type = :type')
            ->andWhere('p.status = :status')
            ->setParameters(['type' => 'post', 'status' => 'publish'])
            ->count();

        self::assertIsInt($count);
    }

    #[Test]
    public function existsReturnsBool(): void
    {
        $exists = (new PostQueryBuilder())
            ->where('p.type = :type')
            ->andWhere('p.id = :id')
            ->setParameters(['type' => 'post', 'id' => 999999])
            ->exists();

        self::assertFalse($exists);
    }
}
