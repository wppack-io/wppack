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

namespace WPPack\Component\Query\Tests\Builder;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Query\Builder\TermQueryBuilder;
use WPPack\Component\Query\Condition\ConditionGroup;
use WPPack\Component\Query\Enum\Order;

final class TermQueryBuilderTest extends TestCase
{
    // ── Standard field conditions via where() ──

    #[Test]
    public function whereTaxonomyEquals(): void
    {
        $builder = new TermQueryBuilder();
        $args = $builder
            ->where('t.taxonomy = :tax')
            ->setParameter('tax', 'category')
            ->toArray();

        self::assertSame('category', $args['taxonomy']);
    }

    #[Test]
    public function whereTaxonomyIn(): void
    {
        $builder = new TermQueryBuilder();
        $args = $builder
            ->where('t.taxonomy IN :taxes')
            ->setParameter('taxes', ['category', 'post_tag'])
            ->toArray();

        self::assertSame(['category', 'post_tag'], $args['taxonomy']);
    }

    #[Test]
    public function whereTaxonomyWithLongPrefix(): void
    {
        $builder = new TermQueryBuilder();
        $args = $builder
            ->where('term.taxonomy = :tax')
            ->setParameter('tax', 'category')
            ->toArray();

        self::assertSame('category', $args['taxonomy']);
    }

    #[Test]
    public function whereIdEquals(): void
    {
        $builder = new TermQueryBuilder();
        $args = $builder
            ->where('t.id = :id')
            ->setParameter('id', 5)
            ->toArray();

        self::assertSame([5], $args['include']);
    }

    #[Test]
    public function whereIdIn(): void
    {
        $builder = new TermQueryBuilder();
        $args = $builder
            ->where('t.id IN :ids')
            ->setParameter('ids', [5, 10])
            ->toArray();

        self::assertSame([5, 10], $args['include']);
    }

    #[Test]
    public function whereIdNotIn(): void
    {
        $builder = new TermQueryBuilder();
        $args = $builder
            ->where('t.id NOT IN :ids')
            ->setParameter('ids', [3])
            ->toArray();

        self::assertSame([3], $args['exclude']);
    }

    #[Test]
    public function whereSlugEquals(): void
    {
        $builder = new TermQueryBuilder();
        $args = $builder
            ->where('t.slug = :slug')
            ->setParameter('slug', 'electronics')
            ->toArray();

        self::assertSame('electronics', $args['slug']);
    }

    #[Test]
    public function whereSlugIn(): void
    {
        $builder = new TermQueryBuilder();
        $args = $builder
            ->where('t.slug IN :slugs')
            ->setParameter('slugs', ['electronics', 'books'])
            ->toArray();

        self::assertSame(['electronics', 'books'], $args['slug']);
    }

    #[Test]
    public function whereParentEquals(): void
    {
        $builder = new TermQueryBuilder();
        $args = $builder
            ->where('t.parent = :parent')
            ->setParameter('parent', 10)
            ->toArray();

        self::assertSame(10, $args['parent']);
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

    // ── setParameters (batch) ──

    #[Test]
    public function setParametersBatch(): void
    {
        $builder = new TermQueryBuilder();
        $args = $builder
            ->where('t.taxonomy = :tax')
            ->andWhere('m.featured = :feat')
            ->setParameters(['tax' => 'category', 'feat' => true])
            ->toArray();

        self::assertSame('category', $args['taxonomy']);
        self::assertArrayHasKey('meta_query', $args);
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
    public function postPrefixIsRejected(): void
    {
        $builder = new TermQueryBuilder();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown prefix "p"');

        $builder->where('p.type = :type');
    }

    // ── Standard field error cases ──

    #[Test]
    public function orWhereWithStandardFieldThrows(): void
    {
        $builder = new TermQueryBuilder();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot be used in orWhere');

        $builder->orWhere('t.taxonomy = :tax');
    }

    #[Test]
    public function unknownTermFieldThrows(): void
    {
        $builder = new TermQueryBuilder();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown term field "unknown"');

        $builder
            ->where('t.unknown = :val')
            ->setParameter('val', 'test')
            ->toArray();
    }

    #[Test]
    public function unsupportedOperatorForTaxonomyThrows(): void
    {
        $builder = new TermQueryBuilder();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported operator "LIKE" for field "term.taxonomy"');

        $builder
            ->where('t.taxonomy LIKE :val')
            ->setParameter('val', 'cat%')
            ->toArray();
    }

    #[Test]
    public function unsupportedOperatorForParentThrows(): void
    {
        $builder = new TermQueryBuilder();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported operator "IN" for field "term.parent"');

        $builder
            ->where('t.parent IN :vals')
            ->setParameter('vals', [1, 2])
            ->toArray();
    }

    // ── Mixed standard fields and meta ──

    #[Test]
    public function standardFieldsAndMetaConditions(): void
    {
        $builder = new TermQueryBuilder();
        $args = $builder
            ->where('t.taxonomy = :tax')
            ->andWhere('m.featured = :feat')
            ->setParameter('tax', 'category')
            ->setParameter('feat', true)
            ->toArray();

        self::assertSame('category', $args['taxonomy']);
        self::assertArrayHasKey('meta_query', $args);
        self::assertSame('featured', $args['meta_query'][0]['key']);
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

    // ── WQL ORDER BY ──

    #[Test]
    public function orderByWqlSingleField(): void
    {
        $builder = new TermQueryBuilder();
        $args = $builder->orderBy('t.name', Order::Asc)->toArray();

        self::assertSame('name', $args['orderby']);
        self::assertSame('ASC', $args['order']);
    }

    #[Test]
    public function orderByWqlMeta(): void
    {
        $builder = new TermQueryBuilder();
        $args = $builder->orderBy('m.sort_order:numeric', Order::Asc)->toArray();

        self::assertSame('sort_order', $args['meta_key']);
        self::assertSame('meta_value_num', $args['orderby']);
        self::assertSame('NUMERIC', $args['meta_type']);
        self::assertSame('ASC', $args['order']);
    }

    #[Test]
    public function addOrderByAppends(): void
    {
        $builder = new TermQueryBuilder();
        $args = $builder
            ->orderBy('t.name', Order::Asc)
            ->addOrderBy('t.count', Order::Desc)
            ->toArray();

        self::assertSame(['name' => 'ASC', 'count' => 'DESC'], $args['orderby']);
    }

    // ── Pagination (Doctrine naming) ──

    #[Test]
    public function setMaxResultsSetsNumber(): void
    {
        $builder = new TermQueryBuilder();
        $args = $builder->setMaxResults(20)->toArray();

        self::assertSame(20, $args['number']);
    }

    #[Test]
    public function setFirstResultSetsOffset(): void
    {
        $builder = new TermQueryBuilder();
        $args = $builder->setFirstResult(10)->toArray();

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
            ->where('t.taxonomy = :tax')
            ->andWhere('m.featured = :feat')
            ->setParameters(['tax' => 'category', 'feat' => true])
            ->hideEmpty()
            ->orderBy('count', Order::Desc)
            ->setMaxResults(10)
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
        $args = $builder
            ->where('t.taxonomy = :tax')
            ->setParameter('tax', 'category')
            ->toArray();

        self::assertArrayNotHasKey('meta_query', $args);
    }

    // ── Execution with non-array returns ──

    #[Test]
    public function getWithInvalidTaxonomyReturnsEmptyResult(): void
    {
        // When taxonomy is invalid, WP_Term_Query::get_terms() returns WP_Error (not array)
        // This tests the !\is_array($terms) fallback branch in get()
        $result = (new TermQueryBuilder())
            ->where('t.taxonomy = :tax')
            ->setParameter('tax', 'nonexistent_taxonomy_' . uniqid())
            ->get();

        self::assertTrue($result->isEmpty());
        self::assertSame(0, $result->total);
    }

    #[Test]
    public function firstWithInvalidTaxonomyReturnsNull(): void
    {
        // When taxonomy is invalid, WP_Term_Query::get_terms() returns WP_Error
        // This tests the !\is_array($terms) fallback branch in first()
        $term = (new TermQueryBuilder())
            ->where('t.taxonomy = :tax')
            ->setParameter('tax', 'nonexistent_taxonomy_' . uniqid())
            ->first();

        self::assertNull($term);
    }

    #[Test]
    public function getIdsWithInvalidTaxonomyReturnsEmptyArray(): void
    {
        // When taxonomy is invalid, WP_Term_Query::get_terms() returns WP_Error
        // This tests the !\is_array($ids) fallback branch in getIds()
        $ids = (new TermQueryBuilder())
            ->where('t.taxonomy = :tax')
            ->setParameter('tax', 'nonexistent_taxonomy_' . uniqid())
            ->getIds();

        self::assertSame([], $ids);
    }

    // ── Ordering with string order ──

    #[Test]
    public function orderByWithStringOrder(): void
    {
        $builder = new TermQueryBuilder();
        $args = $builder->orderBy('name', 'DESC')->toArray();

        self::assertSame('name', $args['orderby']);
        self::assertSame('DESC', $args['order']);
    }

    #[Test]
    public function addOrderByWithStringOrder(): void
    {
        $builder = new TermQueryBuilder();
        $args = $builder
            ->orderBy('t.name', 'ASC')
            ->addOrderBy('t.count', 'DESC')
            ->toArray();

        self::assertSame(['name' => 'ASC', 'count' => 'DESC'], $args['orderby']);
    }

    // ── orWhere with closure ──

    #[Test]
    public function orWhereWithClosure(): void
    {
        $builder = new TermQueryBuilder();
        $args = $builder
            ->where('m.active = :active')
            ->orWhere(function (ConditionGroup $group): void {
                $group->where('m.color = :c1')
                    ->orWhere('m.color = :c2');
            })
            ->setParameter('active', true)
            ->setParameter('c1', 'red')
            ->setParameter('c2', 'blue')
            ->toArray();

        self::assertArrayHasKey('meta_query', $args);
    }

    // ── Additional standard field error cases ──

    #[Test]
    public function unsupportedOperatorForIdThrows(): void
    {
        $builder = new TermQueryBuilder();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported operator "LIKE" for field "term.id"');

        $builder
            ->where('t.id LIKE :val')
            ->setParameter('val', '5%')
            ->toArray();
    }

    #[Test]
    public function unsupportedOperatorForSlugThrows(): void
    {
        $builder = new TermQueryBuilder();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported operator "NOT IN" for field "term.slug"');

        $builder
            ->where('t.slug NOT IN :vals')
            ->setParameter('vals', ['a', 'b'])
            ->toArray();
    }

    // ── Execution methods (WordPress integration) ──

    #[Test]
    public function getReturnsTermQueryResult(): void
    {
        $result = (new TermQueryBuilder())
            ->where('t.taxonomy = :tax')
            ->setParameter('tax', 'category')
            ->get();

        self::assertInstanceOf(\WPPack\Component\Query\Result\TermQueryResult::class, $result);
    }

    #[Test]
    public function getReturnsTotalCount(): void
    {
        $result = (new TermQueryBuilder())
            ->where('t.taxonomy = :tax')
            ->setParameter('tax', 'category')
            ->get();

        self::assertGreaterThanOrEqual(0, $result->total);
    }

    #[Test]
    public function firstReturnsNullableWpTerm(): void
    {
        $term = (new TermQueryBuilder())
            ->where('t.taxonomy = :tax')
            ->andWhere('t.id = :id')
            ->setParameters(['tax' => 'category', 'id' => 999999])
            ->first();

        self::assertNull($term);
    }

    #[Test]
    public function getIdsReturnsArrayOfIntegers(): void
    {
        $ids = (new TermQueryBuilder())
            ->where('t.taxonomy = :tax')
            ->setParameter('tax', 'category')
            ->getIds();

        self::assertIsArray($ids);
    }

    #[Test]
    public function countReturnsInteger(): void
    {
        $count = (new TermQueryBuilder())
            ->where('t.taxonomy = :tax')
            ->setParameter('tax', 'category')
            ->count();

        self::assertIsInt($count);
        self::assertGreaterThanOrEqual(0, $count);
    }

    #[Test]
    public function existsReturnsBool(): void
    {
        $exists = (new TermQueryBuilder())
            ->where('t.taxonomy = :tax')
            ->andWhere('t.id = :id')
            ->setParameters(['tax' => 'category', 'id' => 999999])
            ->exists();

        self::assertFalse($exists);
    }

    #[Test]
    public function existsReturnsTrueWhenTermExists(): void
    {
        $termData = wp_insert_term('exists-test-term', 'category');
        self::assertIsArray($termData);
        $termId = $termData['term_id'];

        try {
            $exists = (new TermQueryBuilder())
                ->hideEmpty(false)
                ->where('t.taxonomy = :tax')
                ->andWhere('t.id = :id')
                ->setParameters(['tax' => 'category', 'id' => $termId])
                ->exists();

            self::assertTrue($exists);
        } finally {
            wp_delete_term($termId, 'category');
        }
    }

    #[Test]
    public function firstReturnsTermWhenExists(): void
    {
        $termData = wp_insert_term('first-test-term', 'category');
        self::assertIsArray($termData);
        $termId = $termData['term_id'];

        try {
            $term = (new TermQueryBuilder())
                ->hideEmpty(false)
                ->where('t.taxonomy = :tax')
                ->andWhere('t.id = :id')
                ->setParameters(['tax' => 'category', 'id' => $termId])
                ->first();

            self::assertInstanceOf(\WP_Term::class, $term);
        } finally {
            wp_delete_term($termId, 'category');
        }
    }

    #[Test]
    public function getIdsReturnsNonEmptyForExistingTerms(): void
    {
        $termData = wp_insert_term('getids-test-term', 'category');
        self::assertIsArray($termData);
        $termId = $termData['term_id'];

        try {
            $ids = (new TermQueryBuilder())
                ->hideEmpty(false)
                ->where('t.taxonomy = :tax')
                ->andWhere('t.id = :id')
                ->setParameters(['tax' => 'category', 'id' => $termId])
                ->getIds();

            self::assertNotEmpty($ids);
            foreach ($ids as $id) {
                self::assertIsInt($id);
            }
        } finally {
            wp_delete_term($termId, 'category');
        }
    }
}
