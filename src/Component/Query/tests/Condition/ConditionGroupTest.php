<?php

declare(strict_types=1);

namespace WpPack\Component\Query\Tests\Condition;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Query\Condition\ConditionGroup;

final class ConditionGroupTest extends TestCase
{
    // ── Empty group ──

    #[Test]
    public function emptyGroupReturnsEmptyMetaQuery(): void
    {
        $group = new ConditionGroup();

        self::assertSame([], $group->toMetaQuery([]));
    }

    #[Test]
    public function emptyGroupReturnsEmptyTaxQuery(): void
    {
        $group = new ConditionGroup();

        self::assertSame([], $group->toTaxQuery([]));
    }

    // ── Single meta condition ──

    #[Test]
    public function singleMetaWhereProducesAndRelation(): void
    {
        $group = new ConditionGroup();
        $group->where('m.featured = :feat');

        self::assertSame([
            'relation' => 'AND',
            ['key' => 'featured', 'value' => true, 'compare' => '='],
        ], $group->toMetaQuery(['feat' => true]));
    }

    #[Test]
    public function metaWhereWithHint(): void
    {
        $group = new ConditionGroup();
        $group->where('m.price:numeric <= :price');

        self::assertSame([
            'relation' => 'AND',
            ['key' => 'price', 'value' => 100, 'compare' => '<=', 'type' => 'NUMERIC'],
        ], $group->toMetaQuery(['price' => 100]));
    }

    // ── Multiple AND conditions ──

    #[Test]
    public function multipleAndConditions(): void
    {
        $group = new ConditionGroup();
        $group->where('m.featured = :feat')
            ->andWhere('m.price:numeric <= :price');

        $result = $group->toMetaQuery(['feat' => true, 'price' => 100]);

        self::assertSame('AND', $result['relation']);
        self::assertCount(3, $result); // relation + 2 conditions
        self::assertSame('featured', $result[0]['key']);
        self::assertSame('price', $result[1]['key']);
    }

    // ── OR conditions ──

    #[Test]
    public function orOnlyConditions(): void
    {
        $group = new ConditionGroup();
        $group->orWhere('m.featured = :feat')
            ->orWhere('m.on_sale = :sale');

        $result = $group->toMetaQuery(['feat' => true, 'sale' => true]);

        self::assertSame('OR', $result['relation']);
        self::assertCount(3, $result); // relation + 2 conditions
        self::assertSame('featured', $result[0]['key']);
        self::assertSame('on_sale', $result[1]['key']);
    }

    // ── Mixed AND/OR ──

    #[Test]
    public function mixedAndOrProducesNestedOrGroup(): void
    {
        $group = new ConditionGroup();
        $group->where('m.status = :status')
            ->orWhere('m.featured = :feat')
            ->orWhere('m.on_sale = :sale');

        $result = $group->toMetaQuery(['status' => 'active', 'feat' => true, 'sale' => true]);

        self::assertSame('AND', $result['relation']);
        self::assertSame('status', $result[0]['key']);
        $orGroup = $result[1];
        self::assertSame('OR', $orGroup['relation']);
        self::assertSame('featured', $orGroup[0]['key']);
        self::assertSame('on_sale', $orGroup[1]['key']);
    }

    // ── Nested groups ──

    #[Test]
    public function nestedGroupWithClosureInAndWhere(): void
    {
        $group = new ConditionGroup();
        $group->where('m.status = :status')
            ->andWhere(function (ConditionGroup $nested): void {
                $nested->where('m.featured = :feat')
                    ->orWhere('m.on_sale = :sale');
            });

        $result = $group->toMetaQuery(['status' => 'active', 'feat' => true, 'sale' => true]);

        self::assertSame('AND', $result['relation']);
        self::assertSame('status', $result[0]['key']);
        $nestedResult = $result[1];
        self::assertArrayHasKey('relation', $nestedResult);
    }

    #[Test]
    public function nestedGroupWithClosureInOrWhere(): void
    {
        $group = new ConditionGroup();
        $group->orWhere('m.category = :cat')
            ->orWhere(function (ConditionGroup $nested): void {
                $nested->where('m.price:numeric >= :min')
                    ->andWhere('m.rating:numeric >= :rating');
            });

        $result = $group->toMetaQuery(['cat' => 'premium', 'min' => 50, 'rating' => 4]);

        self::assertSame('OR', $result['relation']);
        self::assertSame('category', $result[0]['key']);
        $nestedResult = $result[1];
        self::assertSame('AND', $nestedResult['relation']);
    }

    // ── EXISTS / NOT EXISTS ──

    #[Test]
    public function whereExistsAddsExistsCondition(): void
    {
        $group = new ConditionGroup();
        $group->where('m.thumbnail EXISTS');

        self::assertSame([
            'relation' => 'AND',
            ['key' => 'thumbnail', 'value' => '', 'compare' => 'EXISTS'],
        ], $group->toMetaQuery([]));
    }

    #[Test]
    public function whereNotExistsAddsNotExistsCondition(): void
    {
        $group = new ConditionGroup();
        $group->where('m.thumbnail NOT EXISTS');

        self::assertSame([
            'relation' => 'AND',
            ['key' => 'thumbnail', 'value' => '', 'compare' => 'NOT EXISTS'],
        ], $group->toMetaQuery([]));
    }

    // ── Tax conditions ──

    #[Test]
    public function taxConditionProducesTaxQuery(): void
    {
        $group = new ConditionGroup();
        $group->where('t.category IN :cats');

        self::assertSame([
            'relation' => 'AND',
            [
                'taxonomy' => 'category',
                'field' => 'term_id',
                'terms' => [1, 2],
                'operator' => 'IN',
                'include_children' => true,
            ],
        ], $group->toTaxQuery(['cats' => [1, 2]]));
    }

    #[Test]
    public function taxConditionWithSlugHint(): void
    {
        $group = new ConditionGroup();
        $group->where('t.post_tag:slug IN :tags');

        $result = $group->toTaxQuery(['tags' => ['php', 'wordpress']]);

        self::assertSame('slug', $result[0]['field']);
        self::assertSame(['php', 'wordpress'], $result[0]['terms']);
    }

    #[Test]
    public function taxConditionDefaultsToTermId(): void
    {
        $group = new ConditionGroup();
        $group->where('t.category IN :cats');

        $result = $group->toTaxQuery(['cats' => [1]]);

        self::assertSame('term_id', $result[0]['field']);
    }

    #[Test]
    public function taxExistsOperator(): void
    {
        $group = new ConditionGroup();
        $group->where('t.category EXISTS');

        self::assertSame([
            'relation' => 'AND',
            ['taxonomy' => 'category', 'operator' => 'EXISTS'],
        ], $group->toTaxQuery([]));
    }

    #[Test]
    public function taxNotExistsOperator(): void
    {
        $group = new ConditionGroup();
        $group->where('t.category NOT EXISTS');

        self::assertSame([
            'relation' => 'AND',
            ['taxonomy' => 'category', 'operator' => 'NOT EXISTS'],
        ], $group->toTaxQuery([]));
    }

    // ── Mixed meta and tax in same group ──

    #[Test]
    public function mixedPrefixesFilterCorrectly(): void
    {
        $group = new ConditionGroup();
        $group->where('m.featured = :feat')
            ->andWhere('t.category IN :cats');

        $params = ['feat' => true, 'cats' => [1, 2]];

        // meta_query should only contain meta entries
        $metaResult = $group->toMetaQuery($params);
        self::assertCount(2, $metaResult); // relation + 1 condition
        self::assertSame('featured', $metaResult[0]['key']);

        // tax_query should only contain tax entries
        $taxResult = $group->toTaxQuery($params);
        self::assertCount(2, $taxResult); // relation + 1 condition
        self::assertSame('category', $taxResult[0]['taxonomy']);
    }

    #[Test]
    public function metaOnlyGroupReturnEmptyTaxQuery(): void
    {
        $group = new ConditionGroup();
        $group->where('m.featured = :feat');

        self::assertSame([], $group->toTaxQuery(['feat' => true]));
    }

    #[Test]
    public function taxOnlyGroupReturnEmptyMetaQuery(): void
    {
        $group = new ConditionGroup();
        $group->where('t.category IN :cats');

        self::assertSame([], $group->toMetaQuery(['cats' => [1]]));
    }

    // ── Multiple tax conditions ──

    #[Test]
    public function multipleTaxAndConditions(): void
    {
        $group = new ConditionGroup();
        $group->where('t.category IN :cats')
            ->andWhere('t.post_tag:slug IN :tags');

        $result = $group->toTaxQuery(['cats' => [1, 2], 'tags' => ['php']]);

        self::assertSame('AND', $result['relation']);
        self::assertSame('category', $result[0]['taxonomy']);
        self::assertSame('post_tag', $result[1]['taxonomy']);
    }

    #[Test]
    public function multipleTaxOrConditions(): void
    {
        $group = new ConditionGroup();
        $group->orWhere('t.category IN :cats')
            ->orWhere('t.post_tag:slug IN :tags');

        $result = $group->toTaxQuery(['cats' => [1], 'tags' => ['php']]);

        self::assertSame('OR', $result['relation']);
    }

    // ── Parameter validation ──

    #[Test]
    public function throwsOnMissingParameter(): void
    {
        $group = new ConditionGroup();
        $group->where('m.featured = :feat');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Parameter ":feat" is not set');

        $group->toMetaQuery([]);
    }

    // ── Prefix restriction ──

    #[Test]
    public function allowedPrefixesRejectsTaxInMetaOnlyContext(): void
    {
        $group = new ConditionGroup(allowedPrefixes: ['meta']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Prefix "tax" is not allowed');

        $group->where('t.category IN :cats');
    }

    #[Test]
    public function allowedPrefixesAcceptsMetaInMetaOnlyContext(): void
    {
        $group = new ConditionGroup(allowedPrefixes: ['meta']);
        $group->where('m.featured = :feat');

        $result = $group->toMetaQuery(['feat' => true]);

        self::assertSame('AND', $result['relation']);
        self::assertSame('featured', $result[0]['key']);
    }

    // ── Complex scenario ──

    #[Test]
    public function complexNestedConditions(): void
    {
        // WHERE m.status = 'active'
        // AND (m.featured = 1 OR m.on_sale = 1)
        // AND m.price:numeric <= 100
        $group = new ConditionGroup();
        $group->where('m.status = :status')
            ->andWhere(function (ConditionGroup $nested): void {
                $nested->orWhere('m.featured = :feat')
                    ->orWhere('m.on_sale = :sale');
            })
            ->andWhere('m.price:numeric <= :price');

        $result = $group->toMetaQuery([
            'status' => 'active',
            'feat' => true,
            'sale' => true,
            'price' => 100,
        ]);

        self::assertSame('AND', $result['relation']);
        self::assertSame('status', $result[0]['key']);
        self::assertSame('OR', $result[1]['relation']);
        self::assertSame('price', $result[2]['key']);
    }

    #[Test]
    public function whereAndAndWhereAreEquivalent(): void
    {
        $group1 = new ConditionGroup();
        $group1->where('m.key1 = :val');

        $group2 = new ConditionGroup();
        $group2->andWhere('m.key1 = :val');

        self::assertSame(
            $group1->toMetaQuery(['val' => 'v1']),
            $group2->toMetaQuery(['val' => 'v1']),
        );
    }

    #[Test]
    public function nestedGroupInheritsAllowedPrefixes(): void
    {
        $group = new ConditionGroup(allowedPrefixes: ['meta']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Prefix "tax" is not allowed');

        $group->andWhere(function (ConditionGroup $nested): void {
            $nested->where('t.category IN :cats');
        });
    }

    // ── WQL compound expressions ──

    #[Test]
    public function compoundAndInWhereProducesNestedGroup(): void
    {
        $group = new ConditionGroup();
        $group->where('m.featured = :feat AND m.on_sale = :sale');

        $result = $group->toMetaQuery(['feat' => true, 'sale' => true]);

        self::assertSame('AND', $result['relation']);
        // The compound expression becomes a nested AND group
        $nested = $result[0];
        self::assertSame('AND', $nested['relation']);
        self::assertSame('featured', $nested[0]['key']);
        self::assertSame('on_sale', $nested[1]['key']);
    }

    #[Test]
    public function compoundOrInWhereProducesNestedGroup(): void
    {
        $group = new ConditionGroup();
        $group->where('m.featured = :feat OR m.on_sale = :sale');

        $result = $group->toMetaQuery(['feat' => true, 'sale' => true]);

        self::assertSame('AND', $result['relation']);
        $nested = $result[0];
        self::assertSame('AND', $nested['relation']);
        self::assertSame('featured', $nested[0]['key']);
        // The OR part becomes nested inside
    }

    #[Test]
    public function compoundExpressionWithParentheses(): void
    {
        // (m.featured = :feat OR m.on_sale = :sale) AND m.status = :status
        $group = new ConditionGroup();
        $group->where('(m.featured = :feat OR m.on_sale = :sale) AND m.status = :status');

        $result = $group->toMetaQuery(['feat' => true, 'sale' => true, 'status' => 'active']);

        self::assertSame('AND', $result['relation']);
        $nested = $result[0];
        self::assertSame('AND', $nested['relation']);
    }

    #[Test]
    public function compoundMixedPrefixAndIsAllowed(): void
    {
        $group = new ConditionGroup();
        $group->where('m.featured = :feat AND t.category IN :cats');

        $metaResult = $group->toMetaQuery(['feat' => true, 'cats' => [1]]);
        $taxResult = $group->toTaxQuery(['feat' => true, 'cats' => [1]]);

        // Meta query should contain the meta condition
        self::assertNotSame([], $metaResult);
        // Tax query should contain the tax condition
        self::assertNotSame([], $taxResult);
    }

    #[Test]
    public function compoundMixedPrefixOrThrows(): void
    {
        $group = new ConditionGroup();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot mix prefixes');

        $group->where('m.featured = :feat OR t.category IN :cats');
    }

    #[Test]
    public function compoundMixedPrefixInParenthesizedOrThrows(): void
    {
        $group = new ConditionGroup();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot mix prefixes');

        $group->where('(m.featured = :feat OR t.category IN :cats) AND m.status = :status');
    }

    #[Test]
    public function compoundSamePrefixOrIsAllowed(): void
    {
        $group = new ConditionGroup();
        $group->where('(m.featured = :feat OR m.on_sale = :sale) AND m.status = :status');

        $result = $group->toMetaQuery(['feat' => true, 'sale' => true, 'status' => 'active']);

        self::assertSame('AND', $result['relation']);
    }

    #[Test]
    public function compoundExpressionRespectsAllowedPrefixes(): void
    {
        $group = new ConditionGroup(allowedPrefixes: ['meta']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Prefix "tax" is not allowed');

        $group->where('m.feat = :feat AND t.category IN :cats');
    }

    #[Test]
    public function compoundExpressionWithOrWhereType(): void
    {
        $group = new ConditionGroup();
        $group->where('m.status = :status')
            ->orWhere('m.featured = :feat AND m.on_sale = :sale');

        $result = $group->toMetaQuery(['status' => 'active', 'feat' => true, 'sale' => true]);

        self::assertSame('AND', $result['relation']);
        self::assertSame('status', $result[0]['key']);
        // The compound expression is in the OR group
        $orGroup = $result[1];
        self::assertSame('OR', $orGroup['relation']);
    }
}
