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

namespace WPPack\Component\Query\Tests\Wql;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Query\Enum\Order;
use WPPack\Component\Query\Exception\ExceptionInterface;
use WPPack\Component\Query\Exception\InvalidQueryException;
use WPPack\Component\Query\Wql\CompoundExpression;
use WPPack\Component\Query\Wql\ExpressionNode;
use WPPack\Component\Query\Wql\ParsedExpression;
use WPPack\Component\Query\Wql\ParsedOrderBy;
use WPPack\Component\Query\Wql\Token;
use WPPack\Component\Query\Wql\TokenType;

#[CoversClass(Token::class)]
#[CoversClass(TokenType::class)]
#[CoversClass(ParsedExpression::class)]
#[CoversClass(ParsedOrderBy::class)]
#[CoversClass(CompoundExpression::class)]
#[CoversClass(InvalidQueryException::class)]
final class WqlDtosTest extends TestCase
{
    #[Test]
    public function tokenCarriesTypeAndValue(): void
    {
        $token = new Token(TokenType::Condition, 'user.email = "a@b.com"');

        self::assertSame(TokenType::Condition, $token->type);
        self::assertSame('user.email = "a@b.com"', $token->value);
    }

    #[Test]
    public function tokenTypeHasExpectedCases(): void
    {
        self::assertCount(5, TokenType::cases());
        self::assertNotNull(TokenType::Condition);
        self::assertNotNull(TokenType::And);
        self::assertNotNull(TokenType::Or);
        self::assertNotNull(TokenType::LeftParen);
        self::assertNotNull(TokenType::RightParen);
    }

    #[Test]
    public function parsedExpressionCarriesAllFields(): void
    {
        $expr = new ParsedExpression(
            prefix: 'meta',
            key: 'department',
            hint: 'CHAR',
            operator: '=',
            placeholder: '?',
        );

        self::assertSame('meta', $expr->prefix);
        self::assertSame('department', $expr->key);
        self::assertSame('CHAR', $expr->hint);
        self::assertSame('=', $expr->operator);
        self::assertSame('?', $expr->placeholder);
        self::assertInstanceOf(ExpressionNode::class, $expr);
    }

    #[Test]
    public function parsedExpressionAllowsNullHintAndPlaceholder(): void
    {
        $expr = new ParsedExpression(
            prefix: 'tax',
            key: 'category',
            hint: null,
            operator: 'EXISTS',
            placeholder: null,
        );

        self::assertNull($expr->hint);
        self::assertNull($expr->placeholder);
    }

    #[Test]
    public function parsedOrderByCarriesFields(): void
    {
        $orderBy = new ParsedOrderBy(
            prefix: 'meta',
            field: 'priority',
            hint: 'NUMERIC',
            direction: Order::Desc,
        );

        self::assertSame('meta', $orderBy->prefix);
        self::assertSame('priority', $orderBy->field);
        self::assertSame('NUMERIC', $orderBy->hint);
        self::assertSame(Order::Desc, $orderBy->direction);
    }

    #[Test]
    public function parsedOrderByAllowsNullPrefix(): void
    {
        $orderBy = new ParsedOrderBy(
            prefix: null,
            field: 'post_date',
            hint: null,
            direction: Order::Asc,
        );

        self::assertNull($orderBy->prefix);
    }

    #[Test]
    public function compoundExpressionHoldsOperatorAndChildren(): void
    {
        $leaf1 = new ParsedExpression('user', 'email', null, '=', '?');
        $leaf2 = new ParsedExpression('user', 'login', null, '!=', '?');

        $compound = new CompoundExpression('AND', [$leaf1, $leaf2]);

        self::assertSame('AND', $compound->operator);
        self::assertCount(2, $compound->children);
        self::assertInstanceOf(ExpressionNode::class, $compound);
    }

    #[Test]
    public function invalidQueryExceptionIsCoreInvalidArgument(): void
    {
        $e = new InvalidQueryException('bad query');

        self::assertInstanceOf(\InvalidArgumentException::class, $e);
        self::assertInstanceOf(ExceptionInterface::class, $e);
        self::assertSame('bad query', $e->getMessage());
    }
}
