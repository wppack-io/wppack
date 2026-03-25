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
use WpPack\Component\Query\Wql\Tokenizer;
use WpPack\Component\Query\Wql\TokenType;

final class TokenizerTest extends TestCase
{
    private Tokenizer $tokenizer;

    protected function setUp(): void
    {
        $this->tokenizer = new Tokenizer();
    }

    // ── Single condition ──

    #[Test]
    public function tokenizesSingleCondition(): void
    {
        $tokens = $this->tokenizer->tokenize('m.price = :price');

        self::assertCount(1, $tokens);
        self::assertSame(TokenType::Condition, $tokens[0]->type);
        self::assertSame('m.price = :price', $tokens[0]->value);
    }

    #[Test]
    public function tokenizesExistsCondition(): void
    {
        $tokens = $this->tokenizer->tokenize('m.thumbnail EXISTS');

        self::assertCount(1, $tokens);
        self::assertSame(TokenType::Condition, $tokens[0]->type);
        self::assertSame('m.thumbnail EXISTS', $tokens[0]->value);
    }

    // ── Logical AND ──

    #[Test]
    public function tokenizesAndBetweenConditions(): void
    {
        $tokens = $this->tokenizer->tokenize('m.a = :a AND m.b = :b');

        self::assertCount(3, $tokens);
        self::assertSame(TokenType::Condition, $tokens[0]->type);
        self::assertSame('m.a = :a', $tokens[0]->value);
        self::assertSame(TokenType::And, $tokens[1]->type);
        self::assertSame(TokenType::Condition, $tokens[2]->type);
        self::assertSame('m.b = :b', $tokens[2]->value);
    }

    // ── Logical OR ──

    #[Test]
    public function tokenizesOrBetweenConditions(): void
    {
        $tokens = $this->tokenizer->tokenize('m.a = :a OR m.b = :b');

        self::assertCount(3, $tokens);
        self::assertSame(TokenType::Condition, $tokens[0]->type);
        self::assertSame(TokenType::Or, $tokens[1]->type);
        self::assertSame(TokenType::Condition, $tokens[2]->type);
    }

    // ── AND compare operator disambiguation ──

    #[Test]
    public function treatsAndCompareOperatorAsPartOfCondition(): void
    {
        $tokens = $this->tokenizer->tokenize('m.key AND :val');

        self::assertCount(1, $tokens);
        self::assertSame(TokenType::Condition, $tokens[0]->type);
        self::assertSame('m.key AND :val', $tokens[0]->value);
    }

    #[Test]
    public function distinguishesAndCompareFromLogicalAnd(): void
    {
        $tokens = $this->tokenizer->tokenize('m.key AND :val AND m.other = :other');

        self::assertCount(3, $tokens);
        self::assertSame('m.key AND :val', $tokens[0]->value);
        self::assertSame(TokenType::And, $tokens[1]->type);
        self::assertSame('m.other = :other', $tokens[2]->value);
    }

    // ── Parentheses ──

    #[Test]
    public function tokenizesParentheses(): void
    {
        $tokens = $this->tokenizer->tokenize('(m.a = :a OR m.b = :b)');

        self::assertCount(5, $tokens);
        self::assertSame(TokenType::LeftParen, $tokens[0]->type);
        self::assertSame(TokenType::Condition, $tokens[1]->type);
        self::assertSame(TokenType::Or, $tokens[2]->type);
        self::assertSame(TokenType::Condition, $tokens[3]->type);
        self::assertSame(TokenType::RightParen, $tokens[4]->type);
    }

    #[Test]
    public function tokenizesNestedParentheses(): void
    {
        $tokens = $this->tokenizer->tokenize('(m.a = :a OR m.b = :b) AND m.c = :c');

        self::assertCount(7, $tokens);
        self::assertSame(TokenType::LeftParen, $tokens[0]->type);
        self::assertSame(TokenType::Condition, $tokens[1]->type);
        self::assertSame(TokenType::Or, $tokens[2]->type);
        self::assertSame(TokenType::Condition, $tokens[3]->type);
        self::assertSame(TokenType::RightParen, $tokens[4]->type);
        self::assertSame(TokenType::And, $tokens[5]->type);
        self::assertSame(TokenType::Condition, $tokens[6]->type);
    }

    #[Test]
    public function tokenizesAndFollowedByParen(): void
    {
        $tokens = $this->tokenizer->tokenize('m.a = :a AND (m.b = :b OR m.c = :c)');

        self::assertCount(7, $tokens);
        self::assertSame(TokenType::Condition, $tokens[0]->type);
        self::assertSame(TokenType::And, $tokens[1]->type);
        self::assertSame(TokenType::LeftParen, $tokens[2]->type);
        self::assertSame(TokenType::Condition, $tokens[3]->type);
        self::assertSame(TokenType::Or, $tokens[4]->type);
        self::assertSame(TokenType::Condition, $tokens[5]->type);
        self::assertSame(TokenType::RightParen, $tokens[6]->type);
    }

    // ── Multiple conditions ──

    #[Test]
    public function tokenizesThreeConditionsWithAnd(): void
    {
        $tokens = $this->tokenizer->tokenize('m.a = :a AND m.b = :b AND m.c = :c');

        self::assertCount(5, $tokens);
        self::assertSame(TokenType::Condition, $tokens[0]->type);
        self::assertSame(TokenType::And, $tokens[1]->type);
        self::assertSame(TokenType::Condition, $tokens[2]->type);
        self::assertSame(TokenType::And, $tokens[3]->type);
        self::assertSame(TokenType::Condition, $tokens[4]->type);
    }

    // ── Mixed prefixes ──

    #[Test]
    public function tokenizesMixedPrefixes(): void
    {
        $tokens = $this->tokenizer->tokenize('m.a = :a AND t.b IN :b');

        self::assertCount(3, $tokens);
        self::assertSame('m.a = :a', $tokens[0]->value);
        self::assertSame(TokenType::And, $tokens[1]->type);
        self::assertSame('t.b IN :b', $tokens[2]->value);
    }

    // ── Case insensitive ──

    #[Test]
    public function tokenizesCaseInsensitiveLogicalOperators(): void
    {
        $tokens = $this->tokenizer->tokenize('m.a = :a and m.b = :b or m.c = :c');

        self::assertCount(5, $tokens);
        self::assertSame(TokenType::And, $tokens[1]->type);
        self::assertSame(TokenType::Or, $tokens[3]->type);
    }

    // ── Two-word operators ──

    #[Test]
    public function tokenizesConditionWithNotInOperator(): void
    {
        $tokens = $this->tokenizer->tokenize('m.key NOT IN :val AND m.other = :other');

        self::assertCount(3, $tokens);
        self::assertSame('m.key NOT IN :val', $tokens[0]->value);
        self::assertSame(TokenType::And, $tokens[1]->type);
        self::assertSame('m.other = :other', $tokens[2]->value);
    }

    #[Test]
    public function tokenizesConditionWithNotExistsOperator(): void
    {
        $tokens = $this->tokenizer->tokenize('m.key NOT EXISTS AND m.other = :other');

        self::assertCount(3, $tokens);
        self::assertSame('m.key NOT EXISTS', $tokens[0]->value);
        self::assertSame(TokenType::And, $tokens[1]->type);
    }

    // ── Complex expression ──

    #[Test]
    public function tokenizesComplexExpression(): void
    {
        $tokens = $this->tokenizer->tokenize('(m.featured = :feat OR m.on_sale = :sale) AND m.status = :status');

        self::assertCount(7, $tokens);
        self::assertSame(TokenType::LeftParen, $tokens[0]->type);
        self::assertSame('m.featured = :feat', $tokens[1]->value);
        self::assertSame(TokenType::Or, $tokens[2]->type);
        self::assertSame('m.on_sale = :sale', $tokens[3]->value);
        self::assertSame(TokenType::RightParen, $tokens[4]->type);
        self::assertSame(TokenType::And, $tokens[5]->type);
        self::assertSame('m.status = :status', $tokens[6]->value);
    }

    // ── Error cases ──

    #[Test]
    public function throwsOnEmptyExpression(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Expression cannot be empty.');

        $this->tokenizer->tokenize('');
    }

    #[Test]
    public function throwsOnWhitespaceOnlyExpression(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Expression cannot be empty.');

        $this->tokenizer->tokenize('   ');
    }

    // ── Tab whitespace ──

    #[Test]
    public function skipsTabWhitespace(): void
    {
        $tokens = $this->tokenizer->tokenize("m.a\t=\t:a");

        self::assertCount(1, $tokens);
        self::assertSame(TokenType::Condition, $tokens[0]->type);
        self::assertSame("m.a\t=\t:a", $tokens[0]->value);
    }

    #[Test]
    public function tabBetweenAndConditions(): void
    {
        $tokens = $this->tokenizer->tokenize("m.a = :a\tAND\tm.b = :b");

        self::assertCount(3, $tokens);
        self::assertSame(TokenType::Condition, $tokens[0]->type);
        self::assertSame(TokenType::And, $tokens[1]->type);
        self::assertSame(TokenType::Condition, $tokens[2]->type);
    }

    // ── AND/OR at end of expression ──

    #[Test]
    public function andAtEndOfExpressionIsTreatedAsLogical(): void
    {
        // AND at end of expression — isLogicalOperator returns true (afterKeyword is empty)
        $tokens = $this->tokenizer->tokenize('m.a = :a AND');

        self::assertCount(2, $tokens);
        self::assertSame(TokenType::Condition, $tokens[0]->type);
        self::assertSame('m.a = :a', $tokens[0]->value);
        self::assertSame(TokenType::And, $tokens[1]->type);
    }

    #[Test]
    public function orAtEndOfExpressionIsTreatedAsLogical(): void
    {
        $tokens = $this->tokenizer->tokenize('m.a = :a OR');

        self::assertCount(2, $tokens);
        self::assertSame(TokenType::Condition, $tokens[0]->type);
        self::assertSame(TokenType::Or, $tokens[1]->type);
    }

    // ── OR is always logical ──

    #[Test]
    public function orFollowedByNonPrefixIsTreatedAsLogical(): void
    {
        // OR followed by something that is not a '(' and not a prefix pattern
        // is still treated as logical (OR is never a compare operator)
        $tokens = $this->tokenizer->tokenize('m.a = :a OR :b');

        self::assertCount(3, $tokens);
        self::assertSame('m.a = :a', $tokens[0]->value);
        self::assertSame(TokenType::Or, $tokens[1]->type);
        self::assertSame(':b', $tokens[2]->value);
    }

    // ── Word boundary ──

    #[Test]
    public function doesNotMatchOrInsideWords(): void
    {
        $tokens = $this->tokenizer->tokenize('m.color = :color');

        self::assertCount(1, $tokens);
        self::assertSame(TokenType::Condition, $tokens[0]->type);
        self::assertSame('m.color = :color', $tokens[0]->value);
    }

    #[Test]
    public function doesNotMatchAndInsideWords(): void
    {
        $tokens = $this->tokenizer->tokenize('m.brand = :brand');

        self::assertCount(1, $tokens);
        self::assertSame(TokenType::Condition, $tokens[0]->type);
        self::assertSame('m.brand = :brand', $tokens[0]->value);
    }

    // ── Hint with condition ──

    #[Test]
    public function tokenizesConditionWithHint(): void
    {
        $tokens = $this->tokenizer->tokenize('m.price:numeric <= :price AND m.stock:numeric > :stock');

        self::assertCount(3, $tokens);
        self::assertSame('m.price:numeric <= :price', $tokens[0]->value);
        self::assertSame('m.stock:numeric > :stock', $tokens[2]->value);
    }
}
