<?php

declare(strict_types=1);

namespace WpPack\Component\Query\Tests\Condition;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Query\Condition\MetaConditionGroup;
use WpPack\Component\Query\Enum\MetaCompare;
use WpPack\Component\Query\Enum\MetaType;

final class MetaConditionGroupTest extends TestCase
{
    #[Test]
    public function emptyGroupReturnsEmptyArray(): void
    {
        $group = new MetaConditionGroup();

        self::assertSame([], $group->toMetaQuery());
    }

    #[Test]
    public function singleWhereProducesAndRelation(): void
    {
        $group = new MetaConditionGroup();
        $group->where('featured', true);

        self::assertSame([
            'relation' => 'AND',
            ['key' => 'featured', 'value' => true, 'compare' => '='],
        ], $group->toMetaQuery());
    }

    #[Test]
    public function whereWithCompareEnum(): void
    {
        $group = new MetaConditionGroup();
        $group->where('price', 100, MetaCompare::LessThanOrEqual);

        self::assertSame([
            'relation' => 'AND',
            ['key' => 'price', 'value' => 100, 'compare' => '<='],
        ], $group->toMetaQuery());
    }

    #[Test]
    public function whereWithTypeEnum(): void
    {
        $group = new MetaConditionGroup();
        $group->where('price', 100, '<=', MetaType::Numeric);

        self::assertSame([
            'relation' => 'AND',
            ['key' => 'price', 'value' => 100, 'compare' => '<=', 'type' => 'NUMERIC'],
        ], $group->toMetaQuery());
    }

    #[Test]
    public function multipleAndConditions(): void
    {
        $group = new MetaConditionGroup();
        $group->where('featured', true)
            ->andWhere('price', 100, '<=');

        $result = $group->toMetaQuery();

        self::assertSame('AND', $result['relation']);
        self::assertCount(3, $result); // relation + 2 conditions
        self::assertSame('featured', $result[0]['key']);
        self::assertSame('price', $result[1]['key']);
    }

    #[Test]
    public function orOnlyConditions(): void
    {
        $group = new MetaConditionGroup();
        $group->orWhere('featured', true)
            ->orWhere('on_sale', true);

        $result = $group->toMetaQuery();

        self::assertSame('OR', $result['relation']);
        self::assertCount(3, $result); // relation + 2 conditions
        self::assertSame('featured', $result[0]['key']);
        self::assertSame('on_sale', $result[1]['key']);
    }

    #[Test]
    public function mixedAndOrProducesNestedOrGroup(): void
    {
        $group = new MetaConditionGroup();
        $group->where('status', 'active')
            ->orWhere('featured', true)
            ->orWhere('on_sale', true);

        $result = $group->toMetaQuery();

        self::assertSame('AND', $result['relation']);
        // Should have: AND clause + nested OR group
        self::assertSame('status', $result[0]['key']);
        // Last element should be the nested OR group
        $orGroup = $result[1];
        self::assertSame('OR', $orGroup['relation']);
        self::assertSame('featured', $orGroup[0]['key']);
        self::assertSame('on_sale', $orGroup[1]['key']);
    }

    #[Test]
    public function nestedGroupWithClosureInAndWhere(): void
    {
        $group = new MetaConditionGroup();
        $group->where('status', 'active')
            ->andWhere(function (MetaConditionGroup $nested): void {
                $nested->where('featured', true)
                    ->orWhere('on_sale', true);
            });

        $result = $group->toMetaQuery();

        self::assertSame('AND', $result['relation']);
        self::assertSame('status', $result[0]['key']);
        // Second element is the nested group
        $nestedResult = $result[1];
        self::assertArrayHasKey('relation', $nestedResult);
    }

    #[Test]
    public function nestedGroupWithClosureInOrWhere(): void
    {
        $group = new MetaConditionGroup();
        $group->orWhere('category', 'premium')
            ->orWhere(function (MetaConditionGroup $nested): void {
                $nested->where('price', 50, '>=')
                    ->andWhere('rating', 4, '>=');
            });

        $result = $group->toMetaQuery();

        self::assertSame('OR', $result['relation']);
        self::assertSame('category', $result[0]['key']);
        // Second element is the nested AND group
        $nestedResult = $result[1];
        self::assertSame('AND', $nestedResult['relation']);
    }

    #[Test]
    public function whereExistsAddsExistsCondition(): void
    {
        $group = new MetaConditionGroup();
        $group->whereExists('thumbnail');

        self::assertSame([
            'relation' => 'AND',
            ['key' => 'thumbnail', 'value' => '', 'compare' => 'EXISTS'],
        ], $group->toMetaQuery());
    }

    #[Test]
    public function whereNotExistsAddsNotExistsCondition(): void
    {
        $group = new MetaConditionGroup();
        $group->whereNotExists('thumbnail');

        self::assertSame([
            'relation' => 'AND',
            ['key' => 'thumbnail', 'value' => '', 'compare' => 'NOT EXISTS'],
        ], $group->toMetaQuery());
    }

    #[Test]
    public function whereAndAndWhereAreEquivalent(): void
    {
        $group1 = new MetaConditionGroup();
        $group1->where('key1', 'val1');

        $group2 = new MetaConditionGroup();
        $group2->andWhere('key1', 'val1');

        self::assertSame($group1->toMetaQuery(), $group2->toMetaQuery());
    }

    #[Test]
    public function complexNestedConditions(): void
    {
        // WHERE status = 'active'
        // AND (featured = 1 OR on_sale = 1)
        // AND price <= 100
        $group = new MetaConditionGroup();
        $group->where('status', 'active')
            ->andWhere(function (MetaConditionGroup $nested): void {
                $nested->orWhere('featured', true)
                    ->orWhere('on_sale', true);
            })
            ->andWhere('price', 100, '<=');

        $result = $group->toMetaQuery();

        self::assertSame('AND', $result['relation']);
        self::assertSame('status', $result[0]['key']);
        // Nested OR group
        self::assertSame('OR', $result[1]['relation']);
        self::assertSame('price', $result[2]['key']);
    }
}
