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

namespace WPPack\Component\Query\Tests\Condition;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Query\Condition\OrderByGroup;
use WPPack\Component\Query\Enum\Order;

final class OrderByGroupTest extends TestCase
{
    #[Test]
    public function singleStandardFieldArgs(): void
    {
        $group = new OrderByGroup();
        $group->set('date', Order::Desc);

        $metaQuery = [];
        $args = $group->toArgs($metaQuery);

        self::assertSame(['orderby' => 'date', 'order' => 'DESC'], $args);
        self::assertSame([], $metaQuery);
    }

    #[Test]
    public function singleStandardFieldWithPrefixArgs(): void
    {
        $group = new OrderByGroup();
        $group->set('p.date', Order::Desc);

        $metaQuery = [];
        $args = $group->toArgs($metaQuery);

        self::assertSame(['orderby' => 'date', 'order' => 'DESC'], $args);
    }

    #[Test]
    public function singleMetaFieldArgs(): void
    {
        $group = new OrderByGroup();
        $group->set('m.sort_order', Order::Asc);

        $metaQuery = [];
        $args = $group->toArgs($metaQuery);

        self::assertSame('sort_order', $args['meta_key']);
        self::assertSame('meta_value', $args['orderby']);
        self::assertSame('ASC', $args['order']);
        self::assertArrayNotHasKey('meta_type', $args);
    }

    #[Test]
    public function singleMetaNumericFieldArgs(): void
    {
        $group = new OrderByGroup();
        $group->set('m.price:numeric', Order::Desc);

        $metaQuery = [];
        $args = $group->toArgs($metaQuery);

        self::assertSame('price', $args['meta_key']);
        self::assertSame('meta_value_num', $args['orderby']);
        self::assertSame('DESC', $args['order']);
        self::assertSame('NUMERIC', $args['meta_type']);
    }

    #[Test]
    public function multipleStandardFieldsArgs(): void
    {
        $group = new OrderByGroup();
        $group->set('p.date', Order::Desc);
        $group->add('p.title', Order::Asc);

        $metaQuery = [];
        $args = $group->toArgs($metaQuery);

        self::assertSame(['date' => 'DESC', 'title' => 'ASC'], $args['orderby']);
        self::assertArrayNotHasKey('order', $args);
    }

    #[Test]
    public function mixedMetaAndStandardArgs(): void
    {
        $group = new OrderByGroup();
        $group->set('m.price:numeric', Order::Desc);
        $group->add('p.date', Order::Asc);

        $metaQuery = [];
        $args = $group->toArgs($metaQuery);

        self::assertSame([
            '__wppack_ob_price' => 'DESC',
            'date' => 'ASC',
        ], $args['orderby']);

        self::assertArrayHasKey('__wppack_ob_price', $metaQuery);
        self::assertSame('price', $metaQuery['__wppack_ob_price']['key']);
        self::assertSame('NUMERIC', $metaQuery['__wppack_ob_price']['type']);
        self::assertSame('EXISTS', $metaQuery['__wppack_ob_price']['compare']);
    }

    #[Test]
    public function multipleMetaFieldsArgs(): void
    {
        $group = new OrderByGroup();
        $group->set('m.price:numeric', Order::Desc);
        $group->add('m.rating:numeric', Order::Asc);

        $metaQuery = [];
        $args = $group->toArgs($metaQuery);

        self::assertSame([
            '__wppack_ob_price' => 'DESC',
            '__wppack_ob_rating' => 'ASC',
        ], $args['orderby']);

        self::assertArrayHasKey('__wppack_ob_price', $metaQuery);
        self::assertArrayHasKey('__wppack_ob_rating', $metaQuery);
    }

    #[Test]
    public function metaQueryInjection(): void
    {
        $group = new OrderByGroup();
        $group->set('m.price:numeric', Order::Desc);
        $group->add('p.date', Order::Asc);

        $metaQuery = [
            'relation' => 'AND',
            ['key' => 'featured', 'value' => true, 'compare' => '='],
        ];
        $group->toArgs($metaQuery);

        // Existing conditions preserved
        self::assertSame('AND', $metaQuery['relation']);
        self::assertSame('featured', $metaQuery[0]['key']);
        // Named clause injected
        self::assertArrayHasKey('__wppack_ob_price', $metaQuery);
    }

    #[Test]
    public function setReplacesAllClauses(): void
    {
        $group = new OrderByGroup();
        $group->set('p.date', Order::Desc);
        $group->set('p.title', Order::Asc);

        $metaQuery = [];
        $args = $group->toArgs($metaQuery);

        self::assertSame(['orderby' => 'title', 'order' => 'ASC'], $args);
    }

    #[Test]
    public function addAppendsClauses(): void
    {
        $group = new OrderByGroup();
        $group->set('p.date', Order::Desc);
        $group->add('p.title', Order::Asc);

        $metaQuery = [];
        $args = $group->toArgs($metaQuery);

        self::assertSame(['date' => 'DESC', 'title' => 'ASC'], $args['orderby']);
    }

    #[Test]
    public function isEmptyReturnsTrueWhenNoClauses(): void
    {
        $group = new OrderByGroup();

        self::assertTrue($group->isEmpty());
    }

    #[Test]
    public function isEmptyReturnsFalseAfterSet(): void
    {
        $group = new OrderByGroup();
        $group->set('date', Order::Desc);

        self::assertFalse($group->isEmpty());
    }

    #[Test]
    public function singleMetaFieldWithCharHint(): void
    {
        $group = new OrderByGroup();
        $group->set('m.label:char', Order::Asc);

        $metaQuery = [];
        $args = $group->toArgs($metaQuery);

        self::assertSame('label', $args['meta_key']);
        self::assertSame('meta_value', $args['orderby']);
        self::assertSame('ASC', $args['order']);
        self::assertSame('CHAR', $args['meta_type']);
    }

    #[Test]
    public function emptyGroupReturnsEmptyArgs(): void
    {
        $group = new OrderByGroup();

        $metaQuery = [];
        $args = $group->toArgs($metaQuery);

        self::assertSame([], $args);
    }
}
