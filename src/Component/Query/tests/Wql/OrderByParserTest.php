<?php

/*
 * This file is part of the WpPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WpPack\Component\Query\Tests\Wql;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Query\Enum\Order;
use WpPack\Component\Query\Wql\OrderByParser;

final class OrderByParserTest extends TestCase
{
    private OrderByParser $parser;

    protected function setUp(): void
    {
        $this->parser = new OrderByParser();
    }

    #[Test]
    public function parseSingleStandardField(): void
    {
        $result = $this->parser->parse('date', Order::Desc);

        self::assertNull($result->prefix);
        self::assertSame('date', $result->field);
        self::assertNull($result->hint);
        self::assertSame(Order::Desc, $result->direction);
    }

    #[Test]
    public function parseStandardFieldWithPostPrefix(): void
    {
        $result = $this->parser->parse('p.date', Order::Desc);

        self::assertNull($result->prefix);
        self::assertSame('date', $result->field);
        self::assertSame(Order::Desc, $result->direction);
    }

    #[Test]
    public function parseStandardFieldWithUserPrefix(): void
    {
        $result = $this->parser->parse('u.display_name', Order::Asc);

        self::assertNull($result->prefix);
        self::assertSame('display_name', $result->field);
        self::assertSame(Order::Asc, $result->direction);
    }

    #[Test]
    public function parseStandardFieldWithTermPrefix(): void
    {
        $result = $this->parser->parse('t.name', Order::Asc);

        self::assertNull($result->prefix);
        self::assertSame('name', $result->field);
        self::assertSame(Order::Asc, $result->direction);
    }

    #[Test]
    public function parseStandardFieldWithLongPrefix(): void
    {
        $post = $this->parser->parse('post.date', Order::Desc);
        self::assertNull($post->prefix);
        self::assertSame('date', $post->field);

        $user = $this->parser->parse('user.name', Order::Asc);
        self::assertNull($user->prefix);
        self::assertSame('name', $user->field);

        $term = $this->parser->parse('term.slug', Order::Desc);
        self::assertNull($term->prefix);
        self::assertSame('slug', $term->field);
    }

    #[Test]
    public function parseMetaFieldWithHint(): void
    {
        $result = $this->parser->parse('m.price:numeric', Order::Asc);

        self::assertSame('meta', $result->prefix);
        self::assertSame('price', $result->field);
        self::assertSame('numeric', $result->hint);
        self::assertSame(Order::Asc, $result->direction);
    }

    #[Test]
    public function parseMetaFieldWithoutHint(): void
    {
        $result = $this->parser->parse('m.sort_order', Order::Desc);

        self::assertSame('meta', $result->prefix);
        self::assertSame('sort_order', $result->field);
        self::assertNull($result->hint);
        self::assertSame(Order::Desc, $result->direction);
    }

    #[Test]
    public function parseMetaPrefixAlias(): void
    {
        $result = $this->parser->parse('meta.price', Order::Desc);

        self::assertSame('meta', $result->prefix);
        self::assertSame('price', $result->field);
    }

    #[Test]
    public function emptyFieldThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->parser->parse('', Order::Desc);
    }

    #[Test]
    public function invalidPrefixThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown prefix "x"');

        $this->parser->parse('x.field', Order::Desc);
    }

    #[Test]
    public function invalidSyntaxThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->parser->parse('!!!', Order::Desc);
    }
}
