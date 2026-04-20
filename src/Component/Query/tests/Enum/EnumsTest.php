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

namespace WPPack\Component\Query\Tests\Enum;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Query\Enum\MetaCompare;
use WPPack\Component\Query\Enum\MetaType;
use WPPack\Component\Query\Enum\Order;
use WPPack\Component\Query\Enum\PostStatus;
use WPPack\Component\Query\Enum\TaxField;
use WPPack\Component\Query\Enum\TaxOperator;

#[CoversClass(Order::class)]
#[CoversClass(PostStatus::class)]
#[CoversClass(TaxOperator::class)]
#[CoversClass(TaxField::class)]
#[CoversClass(MetaCompare::class)]
#[CoversClass(MetaType::class)]
final class EnumsTest extends TestCase
{
    #[Test]
    public function orderEnumMatchesSqlKeywords(): void
    {
        self::assertSame('ASC', Order::Asc->value);
        self::assertSame('DESC', Order::Desc->value);
        self::assertSame(Order::Asc, Order::from('ASC'));
        self::assertSame(Order::Desc, Order::from('DESC'));
    }

    #[Test]
    public function postStatusCoversStandardWordPressStatuses(): void
    {
        $values = array_map(static fn(PostStatus $case): string => $case->value, PostStatus::cases());

        foreach (['publish', 'draft', 'pending', 'private', 'trash', 'auto-draft', 'inherit', 'future', 'any'] as $expected) {
            self::assertContains($expected, $values, "PostStatus should include '{$expected}'");
        }
    }

    #[Test]
    public function taxOperatorCoversStandardWpQueryOperators(): void
    {
        self::assertSame('IN', TaxOperator::In->value);
        self::assertSame('NOT IN', TaxOperator::NotIn->value);
        self::assertSame('AND', TaxOperator::And->value);
        self::assertSame('EXISTS', TaxOperator::Exists->value);
        self::assertSame('NOT EXISTS', TaxOperator::NotExists->value);
    }

    #[Test]
    public function taxFieldCoversWordPressTermFieldNames(): void
    {
        $values = array_map(static fn(TaxField $case): string => $case->value, TaxField::cases());

        self::assertSame(['term_id', 'name', 'slug', 'term_taxonomy_id'], $values);
    }

    #[Test]
    public function metaCompareCoversSqlComparators(): void
    {
        self::assertSame('=', MetaCompare::Equal->value);
        self::assertSame('!=', MetaCompare::NotEqual->value);
        self::assertSame('LIKE', MetaCompare::Like->value);
        self::assertSame('NOT LIKE', MetaCompare::NotLike->value);
        self::assertSame('BETWEEN', MetaCompare::Between->value);
        self::assertSame('NOT BETWEEN', MetaCompare::NotBetween->value);
        self::assertSame('REGEXP', MetaCompare::RegExp->value);
        self::assertSame('NOT REGEXP', MetaCompare::NotRegExp->value);
        self::assertSame(16, \count(MetaCompare::cases()));
    }

    #[Test]
    public function metaTypeCoversStandardMysqlCastTypes(): void
    {
        $values = array_map(static fn(MetaType $case): string => $case->value, MetaType::cases());

        foreach (['NUMERIC', 'BINARY', 'CHAR', 'DATE', 'DATETIME', 'DECIMAL', 'SIGNED', 'TIME', 'UNSIGNED'] as $expected) {
            self::assertContains($expected, $values);
        }
    }

    #[Test]
    public function invalidValueThrowsForEachEnum(): void
    {
        $this->expectException(\ValueError::class);

        Order::from('BAD');
    }
}
