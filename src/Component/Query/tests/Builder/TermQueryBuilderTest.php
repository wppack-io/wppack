<?php

declare(strict_types=1);

namespace WpPack\Component\Query\Tests\Builder;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Query\Builder\TermQueryBuilder;
use WpPack\Component\Query\Condition\ConditionGroup;
use WpPack\Component\Query\Enum\Order;

final class TermQueryBuilderTest extends TestCase
{
    #[Test]
    public function taxonomySetsTaxonomy(): void
    {
        $builder = new TermQueryBuilder();
        $args = $builder->taxonomy('category')->toArray();

        self::assertSame('category', $args['taxonomy']);
    }

    #[Test]
    public function taxonomySetsArrayOfTaxonomies(): void
    {
        $builder = new TermQueryBuilder();
        $args = $builder->taxonomy(['category', 'post_tag'])->toArray();

        self::assertSame(['category', 'post_tag'], $args['taxonomy']);
    }

    #[Test]
    public function hideEmptySetsFlag(): void
    {
        $builder = new TermQueryBuilder();
        $args = $builder->hideEmpty()->toArray();

        self::assertTrue($args['hide_empty']);
    }

    #[Test]
    public function hideEmptyCanBeDisabled(): void
    {
        $builder = new TermQueryBuilder();
        $args = $builder->hideEmpty(false)->toArray();

        self::assertFalse($args['hide_empty']);
    }

    #[Test]
    public function idSetsSingleTermId(): void
    {
        $builder = new TermQueryBuilder();
        $args = $builder->id(5)->toArray();

        self::assertSame([5], $args['include']);
    }

    #[Test]
    public function idSetsArrayOfTermIds(): void
    {
        $builder = new TermQueryBuilder();
        $args = $builder->id([5, 10])->toArray();

        self::assertSame([5, 10], $args['include']);
    }

    #[Test]
    public function notInSetsExcludedTermIds(): void
    {
        $builder = new TermQueryBuilder();
        $args = $builder->notIn([3])->toArray();

        self::assertSame([3], $args['exclude']);
    }

    #[Test]
    public function parentSetsParent(): void
    {
        $builder = new TermQueryBuilder();
        $args = $builder->parent(10)->toArray();

        self::assertSame(10, $args['parent']);
    }

    #[Test]
    public function childOfSetsChildOf(): void
    {
        $builder = new TermQueryBuilder();
        $args = $builder->childOf(10)->toArray();

        self::assertSame(10, $args['child_of']);
    }

    #[Test]
    public function searchSetsSearchKeyword(): void
    {
        $builder = new TermQueryBuilder();
        $args = $builder->search('electronics')->toArray();

        self::assertSame('electronics', $args['search']);
    }

    #[Test]
    public function slugSetsSingleSlug(): void
    {
        $builder = new TermQueryBuilder();
        $args = $builder->slug('electronics')->toArray();

        self::assertSame('electronics', $args['slug']);
    }

    #[Test]
    public function slugSetsArrayOfSlugs(): void
    {
        $builder = new TermQueryBuilder();
        $args = $builder->slug(['electronics', 'books'])->toArray();

        self::assertSame(['electronics', 'books'], $args['slug']);
    }

    // ── Meta conditions ──

    #[Test]
    public function whereAddsMetaCondition(): void
    {
        $builder = new TermQueryBuilder();
        $args = $builder
            ->where('m.color = :color')
            ->setParameter('color', 'red')
            ->toArray();

        self::assertSame([
            'relation' => 'AND',
            ['key' => 'color', 'value' => 'red', 'compare' => '='],
        ], $args['meta_query']);
    }

    #[Test]
    public function orWhereAddsOrMetaCondition(): void
    {
        $builder = new TermQueryBuilder();
        $args = $builder
            ->orWhere('m.color = :red')
            ->orWhere('m.color = :blue')
            ->setParameter('red', 'red')
            ->setParameter('blue', 'blue')
            ->toArray();

        self::assertSame('OR', $args['meta_query']['relation']);
    }

    #[Test]
    public function nestedWhereWithClosure(): void
    {
        $builder = new TermQueryBuilder();
        $args = $builder
            ->where('m.featured = :feat')
            ->andWhere(function (ConditionGroup $group): void {
                $group->where('m.priority = :p1')
                    ->orWhere('m.priority = :p2');
            })
            ->setParameter('feat', true)
            ->setParameter('p1', 1)
            ->setParameter('p2', 2)
            ->toArray();

        self::assertSame('AND', $args['meta_query']['relation']);
    }

    #[Test]
    public function taxPrefixIsRejected(): void
    {
        $builder = new TermQueryBuilder();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Prefix "tax" is not allowed');

        $builder->where('t.category IN :cats');
    }

    // ── Ordering ──

    #[Test]
    public function orderBySetsOrderByAndOrder(): void
    {
        $builder = new TermQueryBuilder();
        $args = $builder->orderBy('count', Order::Desc)->toArray();

        self::assertSame('count', $args['orderby']);
        self::assertSame('DESC', $args['order']);
    }

    #[Test]
    public function orderByDefaultsToAsc(): void
    {
        $builder = new TermQueryBuilder();
        $args = $builder->orderBy('name')->toArray();

        self::assertSame('ASC', $args['order']);
    }

    // ── Pagination ──

    #[Test]
    public function limitSetsNumber(): void
    {
        $builder = new TermQueryBuilder();
        $args = $builder->limit(20)->toArray();

        self::assertSame(20, $args['number']);
    }

    #[Test]
    public function offsetSetsOffset(): void
    {
        $builder = new TermQueryBuilder();
        $args = $builder->offset(10)->toArray();

        self::assertSame(10, $args['offset']);
    }

    // ── Escape hatch ──

    #[Test]
    public function argSetsArbitraryArgument(): void
    {
        $builder = new TermQueryBuilder();
        $args = $builder->arg('name__like', 'elec')->toArray();

        self::assertSame('elec', $args['name__like']);
    }

    // ── Complex queries ──

    #[Test]
    public function complexQueryBuildsCorrectly(): void
    {
        $builder = new TermQueryBuilder();
        $args = $builder
            ->taxonomy('category')
            ->hideEmpty()
            ->where('m.featured = :feat')
            ->setParameter('feat', true)
            ->orderBy('count', Order::Desc)
            ->limit(10)
            ->toArray();

        self::assertSame('category', $args['taxonomy']);
        self::assertTrue($args['hide_empty']);
        self::assertArrayHasKey('meta_query', $args);
        self::assertSame('count', $args['orderby']);
        self::assertSame('DESC', $args['order']);
        self::assertSame(10, $args['number']);
    }

    #[Test]
    public function emptyBuilderReturnsEmptyArgs(): void
    {
        $builder = new TermQueryBuilder();
        $args = $builder->toArray();

        self::assertSame([], $args);
    }

    #[Test]
    public function noMetaQueryWhenNoConditions(): void
    {
        $builder = new TermQueryBuilder();
        $args = $builder->taxonomy('category')->toArray();

        self::assertArrayNotHasKey('meta_query', $args);
    }

    // ── Execution methods (WordPress integration) ──

    #[Test]
    public function getReturnsTermQueryResult(): void
    {
        if (!class_exists(\WP_Term_Query::class)) {
            self::markTestSkipped('WordPress is not available.');
        }

        $result = (new TermQueryBuilder())
            ->taxonomy('category')
            ->get();

        self::assertInstanceOf(\WpPack\Component\Query\Result\TermQueryResult::class, $result);
    }

    #[Test]
    public function firstReturnsNullableWpTerm(): void
    {
        if (!class_exists(\WP_Term_Query::class)) {
            self::markTestSkipped('WordPress is not available.');
        }

        $term = (new TermQueryBuilder())
            ->taxonomy('category')
            ->id(999999)
            ->first();

        self::assertNull($term);
    }
}
