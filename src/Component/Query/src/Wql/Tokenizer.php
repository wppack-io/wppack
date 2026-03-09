<?php

declare(strict_types=1);

namespace WpPack\Component\Query\Wql;

final class Tokenizer
{
    /**
     * Prefix pattern: prefix.key[:hint] (e.g., m.price:numeric, t.category)
     */
    private const PREFIX_PATTERN = '[a-z]+\.[a-zA-Z0-9_]+(?::[a-zA-Z0-9_]+)?';

    /**
     * Tokenize a WQL expression string into a list of tokens.
     *
     * Uses lookahead to disambiguate logical AND/OR from the meta_query AND compare operator:
     * - AND/OR followed by '(' or a prefix pattern → logical operator
     * - AND followed by ':placeholder' → condition operator (part of condition)
     *
     * @return list<Token>
     */
    public function tokenize(string $expression): array
    {
        $expression = trim($expression);

        if ($expression === '') {
            throw new \InvalidArgumentException('Expression cannot be empty.');
        }

        $tokens = [];
        $pos = 0;
        $length = \strlen($expression);

        while ($pos < $length) {
            // Skip whitespace
            if ($expression[$pos] === ' ' || $expression[$pos] === "\t") {
                ++$pos;
                continue;
            }

            // Left paren
            if ($expression[$pos] === '(') {
                $tokens[] = new Token(TokenType::LeftParen, '(');
                ++$pos;
                continue;
            }

            // Right paren
            if ($expression[$pos] === ')') {
                $tokens[] = new Token(TokenType::RightParen, ')');
                ++$pos;
                continue;
            }

            // Try to match logical AND/OR with lookahead (only at word boundaries)
            $remaining = substr($expression, $pos);

            if (($pos === 0 || !ctype_alnum($expression[$pos - 1])) && preg_match('/^(AND|OR)\b/i', $remaining, $match)) {
                $keyword = strtoupper($match[1]);
                $afterKeyword = trim(substr($remaining, \strlen($match[1])));

                if ($this->isLogicalOperator($keyword, $afterKeyword)) {
                    $tokens[] = new Token(
                        $keyword === 'AND' ? TokenType::And : TokenType::Or,
                        $keyword,
                    );
                    $pos += \strlen($match[1]);
                    continue;
                }
            }

            // Must be start of a condition — consume until logical AND/OR or close paren
            $condition = $this->consumeCondition($expression, $pos);
            $tokens[] = new Token(TokenType::Condition, $condition);
        }

        return $tokens;
    }

    /**
     * Determine if AND/OR at the current position is a logical operator (not a compare operator).
     *
     * Logical AND/OR is followed by:
     * - '(' (opening a group)
     * - A prefix pattern (m.xxx, t.xxx, etc.) indicating a new condition
     *
     * Compare AND is followed by:
     * - ':placeholder' (the value part of the condition)
     * - end of string
     */
    private function isLogicalOperator(string $keyword, string $afterKeyword): bool
    {
        if ($afterKeyword === '') {
            // AND/OR at end of expression — invalid, but let parser handle error
            return true;
        }

        // Followed by '(' → logical
        if ($afterKeyword[0] === '(') {
            return true;
        }

        // Followed by prefix pattern → logical
        if (preg_match('/^' . self::PREFIX_PATTERN . '\s/i', $afterKeyword)) {
            return true;
        }

        // For OR, it's always logical (OR is not a valid meta_query compare operator)
        if ($keyword === 'OR') {
            return true;
        }

        // AND followed by ':placeholder' → compare operator
        return false;
    }

    /**
     * Consume characters from the expression until we hit a logical AND/OR or close paren at the top level.
     */
    private function consumeCondition(string $expression, int &$pos): string
    {
        $length = \strlen($expression);
        $start = $pos;

        while ($pos < $length) {
            $ch = $expression[$pos];

            // Stop at close paren
            if ($ch === ')') {
                break;
            }

            // Check for logical AND/OR (only at word boundaries on both sides)
            if ($pos === $start || !ctype_alnum($expression[$pos - 1])) {
                $remaining = substr($expression, $pos);
                if (preg_match('/^(AND|OR)\b/i', $remaining, $match)) {
                    $keyword = strtoupper($match[1]);
                    $afterKeyword = trim(substr($remaining, \strlen($match[1])));

                    if ($this->isLogicalOperator($keyword, $afterKeyword)) {
                        break;
                    }
                }
            }

            ++$pos;
        }

        $condition = trim(substr($expression, $start, $pos - $start));

        if ($condition === '') {
            throw new \InvalidArgumentException(sprintf(
                'Unexpected token at position %d in expression: "%s".',
                $pos,
                $expression,
            ));
        }

        return $condition;
    }
}
