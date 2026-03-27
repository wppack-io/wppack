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

namespace WpPack\Component\Scim\Filter;

use WpPack\Component\Scim\Exception\InvalidFilterException;

final class FilterParser
{
    private const COMPARISON_OPERATORS = ['eq', 'ne', 'co', 'sw', 'ew', 'pr', 'gt', 'ge', 'lt', 'le'];

    public function parse(string $filter): FilterNode
    {
        $filter = trim($filter);

        if ($filter === '') {
            throw new InvalidFilterException('Empty filter expression.');
        }

        return $this->parseExpression($filter);
    }

    private function parseExpression(string $expression): FilterNode
    {
        // Handle parenthesized groups
        $expression = trim($expression);

        // Split by "or" (lowest precedence)
        $parts = $this->splitByLogicalOperator($expression, 'or');
        if (\count($parts) > 1) {
            $node = $this->parseExpression($parts[0]);
            for ($i = 1; $i < \count($parts); $i++) {
                $node = new LogicalNode('or', $node, $this->parseExpression($parts[$i]));
            }

            return $node;
        }

        // Split by "and"
        $parts = $this->splitByLogicalOperator($expression, 'and');
        if (\count($parts) > 1) {
            $node = $this->parseExpression($parts[0]);
            for ($i = 1; $i < \count($parts); $i++) {
                $node = new LogicalNode('and', $node, $this->parseExpression($parts[$i]));
            }

            return $node;
        }

        // Handle parenthesized expression
        if (str_starts_with($expression, '(') && str_ends_with($expression, ')')) {
            return $this->parseExpression(substr($expression, 1, -1));
        }

        return $this->parseComparison($expression);
    }

    private function parseComparison(string $expression): ComparisonNode
    {
        $expression = trim($expression);

        // Match: attributePath operator [value]
        $pattern = '/^([\w.]+)\s+(eq|ne|co|sw|ew|pr|gt|ge|lt|le)(?:\s+(.+))?$/i';

        if (!preg_match($pattern, $expression, $matches)) {
            throw new InvalidFilterException(sprintf('Invalid filter expression: "%s".', $expression));
        }

        $attributePath = $matches[1];
        $operator = strtolower($matches[2]);
        $value = null;

        if ($operator !== 'pr') {
            if (!isset($matches[3]) || trim($matches[3]) === '') {
                throw new InvalidFilterException(sprintf('Operator "%s" requires a value.', $operator));
            }

            $value = $this->parseValue($matches[3]);
        }

        if (!\in_array($operator, self::COMPARISON_OPERATORS, true)) {
            throw new InvalidFilterException(sprintf('Unknown operator: "%s".', $operator));
        }

        return new ComparisonNode($attributePath, $operator, $value);
    }

    private function parseValue(string $raw): string
    {
        $raw = trim($raw);

        // Quoted string — handle backslash-escaped characters (e.g., "O\"Brien")
        if (str_starts_with($raw, '"') && \strlen($raw) >= 2) {
            $inner = $this->extractQuotedString($raw);
            if ($inner !== null) {
                return $inner;
            }

            throw new InvalidFilterException(sprintf('Unterminated quoted string: %s', $raw));
        }

        // Boolean
        if (strtolower($raw) === 'true' || strtolower($raw) === 'false') {
            return strtolower($raw);
        }

        // Null
        if (strtolower($raw) === 'null') {
            return '';
        }

        return $raw;
    }

    /**
     * Split expression by a logical operator, respecting parentheses and quoted strings.
     *
     * @return list<string>
     */
    private function splitByLogicalOperator(string $expression, string $operator): array
    {
        $parts = [];
        $depth = 0;
        $inQuote = false;
        $current = '';
        $tokens = preg_split('/(\s+)/', $expression, -1, \PREG_SPLIT_DELIM_CAPTURE);

        if ($tokens === false) {
            return [$expression];
        }

        $i = 0;
        $count = \count($tokens);

        while ($i < $count) {
            $token = $tokens[$i];

            if ($token === '') {
                $i++;
                continue;
            }

            // Track quote state (count unescaped quotes)
            $quoteCount = substr_count($token, '"') - substr_count($token, '\\"');
            if ($quoteCount % 2 !== 0) {
                $inQuote = !$inQuote;
            }

            // Track parentheses depth (only outside quotes)
            if (!$inQuote) {
                $depth += substr_count($token, '(') - substr_count($token, ')');
            }

            if ($depth === 0 && !$inQuote && strtolower($token) === $operator) {
                $parts[] = trim($current);
                $current = '';
            } else {
                $current .= $token;
            }

            $i++;
        }

        if (trim($current) !== '') {
            $parts[] = trim($current);
        }

        return $parts;
    }

    /**
     * Extract the content of a JSON-style quoted string, handling backslash escapes.
     *
     * Scans from the opening `"` and finds the matching unescaped closing `"`.
     * Returns the unescaped content, or null if no valid closing quote is found.
     */
    private function extractQuotedString(string $raw): ?string
    {
        $len = \strlen($raw);
        $result = '';

        for ($i = 1; $i < $len; $i++) {
            if ($raw[$i] === '\\' && $i + 1 < $len) {
                $result .= $raw[$i + 1];
                $i++;
                continue;
            }

            if ($raw[$i] === '"') {
                // Valid closing quote — must be at end of string
                return $i === $len - 1 ? $result : null;
            }

            $result .= $raw[$i];
        }

        return null;
    }
}
