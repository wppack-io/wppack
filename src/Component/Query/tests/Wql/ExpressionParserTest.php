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
use WpPack\Component\Query\Wql\ExpressionParser;

final class ExpressionParserTest extends TestCase
{
    private ExpressionParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ExpressionParser();
    }

    // ── Meta prefix ──

    #[Test]
    public function parsesMetaShortPrefix(): void
    {
        $expr = $this->parser->parse('m.price = :price');

        self::assertSame('meta', $expr->prefix);
        self::assertSame('price', $expr->key);
        self::assertNull($expr->hint);
        self::assertSame('=', $expr->operator);
        self::assertSame('price', $expr->placeholder);
    }

    #[Test]
    public function parsesMetaLongPrefix(): void
    {
        $expr = $this->parser->parse('meta.price = :price');

        self::assertSame('meta', $expr->prefix);
        self::assertSame('price', $expr->key);
    }

    // ── Tax prefix ──

    #[Test]
    public function parsesTaxShortPrefix(): void
    {
        $expr = $this->parser->parse('t.category IN :cats');

        self::assertSame('tax', $expr->prefix);
        self::assertSame('category', $expr->key);
        self::assertNull($expr->hint);
        self::assertSame('IN', $expr->operator);
        self::assertSame('cats', $expr->placeholder);
    }

    #[Test]
    public function parsesTaxMediumPrefix(): void
    {
        $expr = $this->parser->parse('tax.category IN :cats');

        self::assertSame('tax', $expr->prefix);
    }

    #[Test]
    public function parsesTaxLongPrefix(): void
    {
        $expr = $this->parser->parse('taxonomy.category IN :cats');

        self::assertSame('tax', $expr->prefix);
    }

    // ── Hints ──

    #[Test]
    public function parsesMetaHint(): void
    {
        $expr = $this->parser->parse('m.price:numeric <= :price');

        self::assertSame('price', $expr->key);
        self::assertSame('numeric', $expr->hint);
        self::assertSame('<=', $expr->operator);
        self::assertSame('price', $expr->placeholder);
    }

    #[Test]
    public function parsesTaxHint(): void
    {
        $expr = $this->parser->parse('t.category:slug IN :cats');

        self::assertSame('category', $expr->key);
        self::assertSame('slug', $expr->hint);
        self::assertSame('IN', $expr->operator);
        self::assertSame('cats', $expr->placeholder);
    }

    // ── All operators ──

    #[Test]
    public function parsesEqualOperator(): void
    {
        $expr = $this->parser->parse('m.key = :val');
        self::assertSame('=', $expr->operator);
    }

    #[Test]
    public function parsesNotEqualOperator(): void
    {
        $expr = $this->parser->parse('m.key != :val');
        self::assertSame('!=', $expr->operator);
    }

    #[Test]
    public function parsesGreaterThanOperator(): void
    {
        $expr = $this->parser->parse('m.key > :val');
        self::assertSame('>', $expr->operator);
    }

    #[Test]
    public function parsesGreaterThanOrEqualOperator(): void
    {
        $expr = $this->parser->parse('m.key >= :val');
        self::assertSame('>=', $expr->operator);
    }

    #[Test]
    public function parsesLessThanOperator(): void
    {
        $expr = $this->parser->parse('m.key < :val');
        self::assertSame('<', $expr->operator);
    }

    #[Test]
    public function parsesLessThanOrEqualOperator(): void
    {
        $expr = $this->parser->parse('m.key <= :val');
        self::assertSame('<=', $expr->operator);
    }

    #[Test]
    public function parsesLikeOperator(): void
    {
        $expr = $this->parser->parse('m.key LIKE :val');
        self::assertSame('LIKE', $expr->operator);
    }

    #[Test]
    public function parsesNotLikeOperator(): void
    {
        $expr = $this->parser->parse('m.key NOT LIKE :val');
        self::assertSame('NOT LIKE', $expr->operator);
    }

    #[Test]
    public function parsesInOperator(): void
    {
        $expr = $this->parser->parse('m.key IN :val');
        self::assertSame('IN', $expr->operator);
    }

    #[Test]
    public function parsesNotInOperator(): void
    {
        $expr = $this->parser->parse('m.key NOT IN :val');
        self::assertSame('NOT IN', $expr->operator);
    }

    #[Test]
    public function parsesBetweenOperator(): void
    {
        $expr = $this->parser->parse('m.key BETWEEN :val');
        self::assertSame('BETWEEN', $expr->operator);
    }

    #[Test]
    public function parsesNotBetweenOperator(): void
    {
        $expr = $this->parser->parse('m.key NOT BETWEEN :val');
        self::assertSame('NOT BETWEEN', $expr->operator);
    }

    #[Test]
    public function parsesRegExpOperator(): void
    {
        $expr = $this->parser->parse('m.key REGEXP :val');
        self::assertSame('REGEXP', $expr->operator);
    }

    #[Test]
    public function parsesNotRegExpOperator(): void
    {
        $expr = $this->parser->parse('m.key NOT REGEXP :val');
        self::assertSame('NOT REGEXP', $expr->operator);
    }

    #[Test]
    public function parsesAndOperator(): void
    {
        $expr = $this->parser->parse('m.key AND :val');
        self::assertSame('AND', $expr->operator);
    }

    // ── EXISTS / NOT EXISTS ──

    #[Test]
    public function parsesExistsOperator(): void
    {
        $expr = $this->parser->parse('m.thumbnail EXISTS');

        self::assertSame('meta', $expr->prefix);
        self::assertSame('thumbnail', $expr->key);
        self::assertSame('EXISTS', $expr->operator);
        self::assertNull($expr->placeholder);
    }

    #[Test]
    public function parsesNotExistsOperator(): void
    {
        $expr = $this->parser->parse('t.category NOT EXISTS');

        self::assertSame('tax', $expr->prefix);
        self::assertSame('category', $expr->key);
        self::assertSame('NOT EXISTS', $expr->operator);
        self::assertNull($expr->placeholder);
    }

    // ── Case insensitive operators ──

    #[Test]
    public function parsesLowerCaseOperator(): void
    {
        $expr = $this->parser->parse('m.key in :val');
        self::assertSame('IN', $expr->operator);
    }

    #[Test]
    public function parsesMixedCaseOperator(): void
    {
        $expr = $this->parser->parse('m.key Not In :val');
        self::assertSame('NOT IN', $expr->operator);
    }

    // ── Error cases ──

    #[Test]
    public function throwsOnEmptyExpression(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Expression cannot be empty.');

        $this->parser->parse('');
    }

    #[Test]
    public function throwsOnUnknownPrefix(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown prefix "x"');

        $this->parser->parse('x.key = :val');
    }

    #[Test]
    public function throwsOnInvalidSyntax(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid expression syntax');

        $this->parser->parse('invalid');
    }

    #[Test]
    public function throwsOnMissingPlaceholder(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('requires a placeholder');

        $this->parser->parse('m.key =');
    }

    #[Test]
    public function throwsOnPlaceholderWithoutColon(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Placeholder must start with ":"');

        $this->parser->parse('m.key = val');
    }

    #[Test]
    public function throwsOnExistsWithPlaceholder(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('does not accept a placeholder');

        $this->parser->parse('m.key EXISTS :val');
    }

    #[Test]
    public function throwsOnUnknownOperator(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown operator');

        $this->parser->parse('m.key INVALID :val');
    }

    // ── Underscore in key names ──

    #[Test]
    public function parsesKeyWithUnderscore(): void
    {
        $expr = $this->parser->parse('m.post_tag = :tag');

        self::assertSame('post_tag', $expr->key);
    }

    #[Test]
    public function parsesPlaceholderWithUnderscore(): void
    {
        $expr = $this->parser->parse('m.key = :my_value');

        self::assertSame('my_value', $expr->placeholder);
    }

    // ── Custom prefix map ──

    #[Test]
    public function customPrefixMapParsesPostPrefix(): void
    {
        $parser = new ExpressionParser([
            'm' => 'meta',
            'meta' => 'meta',
            'p' => 'post',
            'post' => 'post',
            't' => 'tax',
        ]);
        $expr = $parser->parse('p.type = :type');

        self::assertSame('post', $expr->prefix);
        self::assertSame('type', $expr->key);
        self::assertSame('=', $expr->operator);
        self::assertSame('type', $expr->placeholder);
    }

    #[Test]
    public function customPrefixMapParsesUserPrefix(): void
    {
        $parser = new ExpressionParser([
            'm' => 'meta',
            'u' => 'user',
            'user' => 'user',
        ]);
        $expr = $parser->parse('u.role = :role');

        self::assertSame('user', $expr->prefix);
        self::assertSame('role', $expr->key);
    }

    #[Test]
    public function customPrefixMapParsesTermPrefix(): void
    {
        $parser = new ExpressionParser([
            'm' => 'meta',
            't' => 'term',
            'term' => 'term',
        ]);
        $expr = $parser->parse('t.taxonomy = :tax');

        self::assertSame('term', $expr->prefix);
        self::assertSame('taxonomy', $expr->key);
    }

    #[Test]
    public function customPrefixMapRejectsUnknown(): void
    {
        $parser = new ExpressionParser([
            'm' => 'meta',
            'u' => 'user',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown prefix "p"');

        $parser->parse('p.type = :type');
    }
}
