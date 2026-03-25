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

namespace WpPack\Component\Query\Wql;

/**
 * Recursive descent parser for WQL (WordPress Query Language) compound expressions.
 *
 * Grammar:
 *   expression = or_expr
 *   or_expr    = and_expr ( 'OR' and_expr )*
 *   and_expr   = primary ( 'AND' primary )*
 *   primary    = '(' expression ')' | condition
 *
 * AND binds tighter than OR (SQL-standard precedence).
 */
final class WqlParser
{
    private readonly Tokenizer $tokenizer;
    private readonly ExpressionParser $expressionParser;

    public function __construct(?ExpressionParser $expressionParser = null)
    {
        $this->tokenizer = new Tokenizer();
        $this->expressionParser = $expressionParser ?? new ExpressionParser();
    }

    /**
     * Parse a WQL expression string into an AST node.
     *
     * Returns ParsedExpression for single conditions (backward compatible),
     * or CompoundExpression for compound conditions with AND/OR.
     */
    public function parse(string $expression): ExpressionNode
    {
        $tokens = $this->tokenizer->tokenize($expression);
        $pos = 0;

        $node = $this->parseOrExpression($tokens, $pos);

        if ($pos < \count($tokens)) {
            throw new \InvalidArgumentException(sprintf(
                'Unexpected token "%s" at position %d in expression: "%s".',
                $tokens[$pos]->value,
                $pos,
                $expression,
            ));
        }

        return $node;
    }

    /**
     * or_expr = and_expr ( 'OR' and_expr )*
     *
     * @param list<Token> $tokens
     */
    private function parseOrExpression(array $tokens, int &$pos): ExpressionNode
    {
        $children = [$this->parseAndExpression($tokens, $pos)];

        while ($pos < \count($tokens) && $tokens[$pos]->type === TokenType::Or) {
            ++$pos; // consume OR
            $children[] = $this->parseAndExpression($tokens, $pos);
        }

        if (\count($children) === 1) {
            return $children[0];
        }

        return new CompoundExpression('OR', $children);
    }

    /**
     * and_expr = primary ( 'AND' primary )*
     *
     * @param list<Token> $tokens
     */
    private function parseAndExpression(array $tokens, int &$pos): ExpressionNode
    {
        $children = [$this->parsePrimary($tokens, $pos)];

        while ($pos < \count($tokens) && $tokens[$pos]->type === TokenType::And) {
            ++$pos; // consume AND
            $children[] = $this->parsePrimary($tokens, $pos);
        }

        if (\count($children) === 1) {
            return $children[0];
        }

        return new CompoundExpression('AND', $children);
    }

    /**
     * primary = '(' expression ')' | condition
     *
     * @param list<Token> $tokens
     */
    private function parsePrimary(array $tokens, int &$pos): ExpressionNode
    {
        if ($pos >= \count($tokens)) {
            throw new \InvalidArgumentException('Unexpected end of expression.');
        }

        $token = $tokens[$pos];

        // Parenthesized expression
        if ($token->type === TokenType::LeftParen) {
            ++$pos; // consume '('

            $node = $this->parseOrExpression($tokens, $pos);

            if ($pos >= \count($tokens) || $tokens[$pos]->type !== TokenType::RightParen) {
                throw new \InvalidArgumentException('Missing closing parenthesis.');
            }

            ++$pos; // consume ')'

            return $node;
        }

        // Condition
        if ($token->type === TokenType::Condition) {
            ++$pos; // consume condition

            return $this->expressionParser->parse($token->value);
        }

        throw new \InvalidArgumentException(sprintf(
            'Unexpected token "%s". Expected a condition or "(".',
            $token->value,
        ));
    }
}
