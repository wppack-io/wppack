<?php

declare(strict_types=1);

namespace WpPack\Component\Query\Tests\Wql;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Query\Wql\CompoundExpression;
use WpPack\Component\Query\Wql\ParsedExpression;
use WpPack\Component\Query\Wql\WqlParser;

final class WqlParserTest extends TestCase
{
    private WqlParser $parser;

    protected function setUp(): void
    {
        $this->parser = new WqlParser();
    }

    // ── Single condition (backward compatible) ──

    #[Test]
    public function parsesSingleConditionIntoParsedExpression(): void
    {
        $node = $this->parser->parse('m.price = :price');

        self::assertInstanceOf(ParsedExpression::class, $node);
        self::assertSame('meta', $node->prefix);
        self::assertSame('price', $node->key);
        self::assertSame('=', $node->operator);
        self::assertSame('price', $node->placeholder);
    }

    #[Test]
    public function parsesSingleExistsCondition(): void
    {
        $node = $this->parser->parse('m.thumbnail EXISTS');

        self::assertInstanceOf(ParsedExpression::class, $node);
        self::assertSame('EXISTS', $node->operator);
        self::assertNull($node->placeholder);
    }

    #[Test]
    public function parsesSingleAndCompareOperator(): void
    {
        $node = $this->parser->parse('m.key AND :val');

        self::assertInstanceOf(ParsedExpression::class, $node);
        self::assertSame('AND', $node->operator);
        self::assertSame('val', $node->placeholder);
    }

    // ── AND compound ──

    #[Test]
    public function parsesAndCompoundExpression(): void
    {
        $node = $this->parser->parse('m.a = :a AND m.b = :b');

        self::assertInstanceOf(CompoundExpression::class, $node);
        self::assertSame('AND', $node->operator);
        self::assertCount(2, $node->children);
        self::assertInstanceOf(ParsedExpression::class, $node->children[0]);
        self::assertInstanceOf(ParsedExpression::class, $node->children[1]);
        self::assertSame('a', $node->children[0]->key);
        self::assertSame('b', $node->children[1]->key);
    }

    #[Test]
    public function flattensAndChain(): void
    {
        $node = $this->parser->parse('m.a = :a AND m.b = :b AND m.c = :c');

        self::assertInstanceOf(CompoundExpression::class, $node);
        self::assertSame('AND', $node->operator);
        self::assertCount(3, $node->children);
        self::assertSame('a', $node->children[0]->key);
        self::assertSame('b', $node->children[1]->key);
        self::assertSame('c', $node->children[2]->key);
    }

    // ── OR compound ──

    #[Test]
    public function parsesOrCompoundExpression(): void
    {
        $node = $this->parser->parse('m.a = :a OR m.b = :b');

        self::assertInstanceOf(CompoundExpression::class, $node);
        self::assertSame('OR', $node->operator);
        self::assertCount(2, $node->children);
    }

    #[Test]
    public function flattensOrChain(): void
    {
        $node = $this->parser->parse('m.a = :a OR m.b = :b OR m.c = :c');

        self::assertInstanceOf(CompoundExpression::class, $node);
        self::assertSame('OR', $node->operator);
        self::assertCount(3, $node->children);
    }

    // ── Operator precedence ──

    #[Test]
    public function andBindsTighterThanOr(): void
    {
        // m.a OR m.b AND m.c → m.a OR (m.b AND m.c)
        $node = $this->parser->parse('m.a = :a OR m.b = :b AND m.c = :c');

        self::assertInstanceOf(CompoundExpression::class, $node);
        self::assertSame('OR', $node->operator);
        self::assertCount(2, $node->children);

        // First child: simple ParsedExpression
        self::assertInstanceOf(ParsedExpression::class, $node->children[0]);
        self::assertSame('a', $node->children[0]->key);

        // Second child: AND compound
        self::assertInstanceOf(CompoundExpression::class, $node->children[1]);
        self::assertSame('AND', $node->children[1]->operator);
        self::assertCount(2, $node->children[1]->children);
    }

    // ── Parentheses ──

    #[Test]
    public function parenthesesOverridePrecedence(): void
    {
        // (m.a OR m.b) AND m.c
        $node = $this->parser->parse('(m.a = :a OR m.b = :b) AND m.c = :c');

        self::assertInstanceOf(CompoundExpression::class, $node);
        self::assertSame('AND', $node->operator);
        self::assertCount(2, $node->children);

        // First child: OR compound (from parentheses)
        self::assertInstanceOf(CompoundExpression::class, $node->children[0]);
        self::assertSame('OR', $node->children[0]->operator);

        // Second child: simple ParsedExpression
        self::assertInstanceOf(ParsedExpression::class, $node->children[1]);
    }

    #[Test]
    public function singleConditionInParenthesesIsUnwrapped(): void
    {
        $node = $this->parser->parse('(m.a = :a)');

        self::assertInstanceOf(ParsedExpression::class, $node);
        self::assertSame('a', $node->key);
    }

    #[Test]
    public function nestedParentheses(): void
    {
        // ((m.a OR m.b) AND m.c) OR m.d
        $node = $this->parser->parse('((m.a = :a OR m.b = :b) AND m.c = :c) OR m.d = :d');

        self::assertInstanceOf(CompoundExpression::class, $node);
        self::assertSame('OR', $node->operator);
        self::assertCount(2, $node->children);

        // First child: AND compound
        $andNode = $node->children[0];
        self::assertInstanceOf(CompoundExpression::class, $andNode);
        self::assertSame('AND', $andNode->operator);

        // Inside AND: first child is OR compound
        self::assertInstanceOf(CompoundExpression::class, $andNode->children[0]);
        self::assertSame('OR', $andNode->children[0]->operator);
    }

    // ── Mixed prefixes ──

    #[Test]
    public function parsesMixedPrefixesInAnd(): void
    {
        $node = $this->parser->parse('m.a = :a AND t.b IN :b');

        self::assertInstanceOf(CompoundExpression::class, $node);
        self::assertSame('AND', $node->operator);

        $first = $node->children[0];
        $second = $node->children[1];
        self::assertInstanceOf(ParsedExpression::class, $first);
        self::assertInstanceOf(ParsedExpression::class, $second);
        self::assertSame('meta', $first->prefix);
        self::assertSame('tax', $second->prefix);
    }

    // ── AND compare operator disambiguation ──

    #[Test]
    public function andCompareFollowedByLogicalAnd(): void
    {
        $node = $this->parser->parse('m.key AND :val AND m.other = :other');

        self::assertInstanceOf(CompoundExpression::class, $node);
        self::assertSame('AND', $node->operator);
        self::assertCount(2, $node->children);

        // First child: m.key AND :val
        $first = $node->children[0];
        self::assertInstanceOf(ParsedExpression::class, $first);
        self::assertSame('AND', $first->operator);
        self::assertSame('val', $first->placeholder);

        // Second child: m.other = :other
        $second = $node->children[1];
        self::assertInstanceOf(ParsedExpression::class, $second);
        self::assertSame('other', $second->key);
    }

    // ── Hints ──

    #[Test]
    public function parsesConditionsWithHints(): void
    {
        $node = $this->parser->parse('m.price:numeric <= :price AND m.rating:numeric >= :rating');

        self::assertInstanceOf(CompoundExpression::class, $node);
        self::assertSame('AND', $node->operator);
        self::assertCount(2, $node->children);

        $first = $node->children[0];
        self::assertInstanceOf(ParsedExpression::class, $first);
        self::assertSame('numeric', $first->hint);

        $second = $node->children[1];
        self::assertInstanceOf(ParsedExpression::class, $second);
        self::assertSame('numeric', $second->hint);
    }

    // ── Complex real-world expressions ──

    #[Test]
    public function parsesComplexProductQuery(): void
    {
        $node = $this->parser->parse('(m.featured = :feat OR m.on_sale = :sale) AND m.status = :status');

        self::assertInstanceOf(CompoundExpression::class, $node);
        self::assertSame('AND', $node->operator);
        self::assertCount(2, $node->children);

        $orGroup = $node->children[0];
        self::assertInstanceOf(CompoundExpression::class, $orGroup);
        self::assertSame('OR', $orGroup->operator);
        self::assertCount(2, $orGroup->children);

        $status = $node->children[1];
        self::assertInstanceOf(ParsedExpression::class, $status);
        self::assertSame('status', $status->key);
    }

    // ── Error cases ──

    #[Test]
    public function throwsOnMissingClosingParen(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing closing parenthesis');

        $this->parser->parse('(m.a = :a OR m.b = :b');
    }

    #[Test]
    public function throwsOnUnexpectedClosingParen(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->parser->parse('m.a = :a) OR m.b = :b');
    }

    #[Test]
    public function throwsOnEmptyParens(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->parser->parse('()');
    }

    #[Test]
    public function throwsOnTrailingAnd(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->parser->parse('m.a = :a AND');
    }

    #[Test]
    public function throwsOnLeadingOr(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->parser->parse('OR m.a = :a');
    }

    // ── Case insensitive ──

    #[Test]
    public function parsesCaseInsensitiveLogicalOperators(): void
    {
        $node = $this->parser->parse('m.a = :a or m.b = :b and m.c = :c');

        self::assertInstanceOf(CompoundExpression::class, $node);
        self::assertSame('OR', $node->operator);
    }
}
