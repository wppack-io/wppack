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

namespace WPPack\Component\Scim\Tests\Filter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Scim\Exception\InvalidFilterException;
use WPPack\Component\Scim\Filter\ComparisonNode;
use WPPack\Component\Scim\Filter\FilterParser;
use WPPack\Component\Scim\Filter\LogicalNode;

#[CoversClass(FilterParser::class)]
#[CoversClass(ComparisonNode::class)]
#[CoversClass(LogicalNode::class)]
final class FilterParserTest extends TestCase
{
    private FilterParser $parser;

    protected function setUp(): void
    {
        $this->parser = new FilterParser();
    }

    #[Test]
    public function parsesSimpleEquality(): void
    {
        $node = $this->parser->parse('userName eq "bjensen"');

        self::assertInstanceOf(ComparisonNode::class, $node);
        self::assertSame('userName', $node->attributePath);
        self::assertSame('eq', $node->operator);
        self::assertSame('bjensen', $node->value);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function comparisonOperatorProvider(): iterable
    {
        yield 'eq' => ['userName eq "bjensen"', 'eq'];
        yield 'ne' => ['userName ne "bjensen"', 'ne'];
        yield 'co' => ['userName co "jen"', 'co'];
        yield 'sw' => ['userName sw "b"', 'sw'];
        yield 'ew' => ['userName ew "sen"', 'ew'];
        yield 'gt' => ['meta.created gt "2020-01-01"', 'gt'];
        yield 'ge' => ['meta.created ge "2020-01-01"', 'ge'];
        yield 'lt' => ['meta.created lt "2020-01-01"', 'lt'];
        yield 'le' => ['meta.created le "2020-01-01"', 'le'];
    }

    #[Test]
    #[DataProvider('comparisonOperatorProvider')]
    public function parsesEveryComparisonOperator(string $filter, string $expectedOperator): void
    {
        $node = $this->parser->parse($filter);

        self::assertInstanceOf(ComparisonNode::class, $node);
        self::assertSame($expectedOperator, $node->operator);
    }

    #[Test]
    public function prOperatorHasNullValue(): void
    {
        $node = $this->parser->parse('emails pr');

        self::assertInstanceOf(ComparisonNode::class, $node);
        self::assertSame('pr', $node->operator);
        self::assertSame('emails', $node->attributePath);
        self::assertNull($node->value);
    }

    #[Test]
    public function caseInsensitiveOperator(): void
    {
        $node = $this->parser->parse('userName EQ "bjensen"');

        self::assertInstanceOf(ComparisonNode::class, $node);
        self::assertSame('eq', $node->operator);
    }

    #[Test]
    public function dottedAttributePath(): void
    {
        $node = $this->parser->parse('emails.value eq "bjensen@example.com"');

        self::assertInstanceOf(ComparisonNode::class, $node);
        self::assertSame('emails.value', $node->attributePath);
    }

    #[Test]
    public function conjunction(): void
    {
        $node = $this->parser->parse('userName eq "bjensen" and active eq "true"');

        self::assertInstanceOf(LogicalNode::class, $node);
        self::assertSame('and', $node->operator);
        self::assertInstanceOf(ComparisonNode::class, $node->left);
        self::assertSame('userName', $node->left->attributePath);
        self::assertInstanceOf(ComparisonNode::class, $node->right);
        self::assertSame('active', $node->right->attributePath);
    }

    #[Test]
    public function disjunction(): void
    {
        $node = $this->parser->parse('userName eq "a" or userName eq "b"');

        self::assertInstanceOf(LogicalNode::class, $node);
        self::assertSame('or', $node->operator);
    }

    #[Test]
    public function orHasLowerPrecedenceThanAnd(): void
    {
        // a and b or c → (a and b) or c
        $node = $this->parser->parse('userName eq "a" and active eq "true" or userName eq "b"');

        self::assertInstanceOf(LogicalNode::class, $node);
        self::assertSame('or', $node->operator, 'or is the top-level node because it has lower precedence');
        self::assertInstanceOf(LogicalNode::class, $node->left);
        self::assertSame('and', $node->left->operator);
        self::assertInstanceOf(ComparisonNode::class, $node->right);
        self::assertSame('b', $node->right->value);
    }

    #[Test]
    public function parenthesesOverridePrecedence(): void
    {
        // a and (b or c) → and, left=a, right=(b or c)
        $node = $this->parser->parse('userName eq "a" and (active eq "true" or userName eq "b")');

        self::assertInstanceOf(LogicalNode::class, $node);
        self::assertSame('and', $node->operator);
        self::assertInstanceOf(LogicalNode::class, $node->right);
        self::assertSame('or', $node->right->operator);
    }

    #[Test]
    public function quotedStringWithEscapedQuote(): void
    {
        $node = $this->parser->parse('displayName eq "O\\"Brien"');

        self::assertInstanceOf(ComparisonNode::class, $node);
        self::assertSame('O"Brien', $node->value);
    }

    #[Test]
    public function quotedStringWithEscapedBackslash(): void
    {
        $node = $this->parser->parse('displayName eq "a\\\\b"');

        self::assertInstanceOf(ComparisonNode::class, $node);
        self::assertSame('a\\b', $node->value);
    }

    #[Test]
    public function unquotedBooleanLowercasedAsValue(): void
    {
        $node = $this->parser->parse('active eq TRUE');

        self::assertInstanceOf(ComparisonNode::class, $node);
        self::assertSame('true', $node->value);
    }

    #[Test]
    public function nullKeywordBecomesEmptyString(): void
    {
        $node = $this->parser->parse('nickName eq null');

        self::assertInstanceOf(ComparisonNode::class, $node);
        self::assertSame('', $node->value);
    }

    #[Test]
    public function unquotedNumberPassedThrough(): void
    {
        $node = $this->parser->parse('age gt 18');

        self::assertInstanceOf(ComparisonNode::class, $node);
        self::assertSame('18', $node->value);
    }

    #[Test]
    public function trimsLeadingAndTrailingWhitespace(): void
    {
        $node = $this->parser->parse('   userName eq "bjensen"   ');

        self::assertInstanceOf(ComparisonNode::class, $node);
        self::assertSame('userName', $node->attributePath);
    }

    #[Test]
    public function emptyFilterThrows(): void
    {
        $this->expectException(InvalidFilterException::class);
        $this->parser->parse('');
    }

    #[Test]
    public function whitespaceOnlyFilterThrows(): void
    {
        $this->expectException(InvalidFilterException::class);
        $this->parser->parse('   ');
    }

    #[Test]
    public function missingValueThrows(): void
    {
        $this->expectException(InvalidFilterException::class);
        $this->expectExceptionMessage('requires a value');
        $this->parser->parse('userName eq');
    }

    #[Test]
    public function unknownOperatorThrows(): void
    {
        $this->expectException(InvalidFilterException::class);
        $this->parser->parse('userName like "bjensen"');
    }

    #[Test]
    public function unterminatedQuotedStringThrows(): void
    {
        $this->expectException(InvalidFilterException::class);
        $this->expectExceptionMessage('Unterminated quoted string');
        $this->parser->parse('userName eq "bjensen');
    }

    #[Test]
    public function quotedStringWithContentAfterClosingQuoteIsInvalid(): void
    {
        $this->expectException(InvalidFilterException::class);
        // `"a"b` — stray characters after closing quote
        $this->parser->parse('userName eq "a"b');
    }

    #[Test]
    public function quotedStringRespectsOperatorKeywordInside(): void
    {
        // The word "and" inside a quoted value must not split the expression.
        $node = $this->parser->parse('displayName eq "Foo and Bar"');

        self::assertInstanceOf(ComparisonNode::class, $node);
        self::assertSame('Foo and Bar', $node->value);
    }

    #[Test]
    public function parenthesesInQuotedValueDoNotAffectPrecedence(): void
    {
        // The `(` and `)` inside the quoted value must not confuse the
        // paren-depth tracker.
        $node = $this->parser->parse('displayName eq "foo (bar) baz" and active eq "true"');

        self::assertInstanceOf(LogicalNode::class, $node);
        self::assertSame('and', $node->operator);
    }
}
