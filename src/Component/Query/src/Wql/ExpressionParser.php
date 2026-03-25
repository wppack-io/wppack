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

final class ExpressionParser
{
    private const DEFAULT_PREFIX_MAP = [
        'm' => 'meta',
        'meta' => 'meta',
        't' => 'tax',
        'tax' => 'tax',
        'taxonomy' => 'tax',
    ];

    private const VALID_OPERATORS = [
        '=', '!=', '>', '>=', '<', '<=',
        'LIKE', 'NOT LIKE',
        'IN', 'NOT IN',
        'BETWEEN', 'NOT BETWEEN',
        'EXISTS', 'NOT EXISTS',
        'REGEXP', 'NOT REGEXP',
        'AND',
    ];

    /** @var array<string, string> */
    private readonly array $prefixMap;

    /**
     * @param array<string, string>|null $prefixMap Custom prefix map. Defaults to meta/tax prefixes.
     */
    public function __construct(?array $prefixMap = null)
    {
        $this->prefixMap = $prefixMap ?? self::DEFAULT_PREFIX_MAP;
    }

    /**
     * Parse an expression string into a ParsedExpression value object.
     *
     * Format: <prefix>.<key>[:<hint>] <operator> [:<placeholder>]
     *
     * Examples:
     *   m.price:numeric <= :price
     *   t.category IN :cats
     *   m.thumbnail EXISTS
     */
    public function parse(string $expression): ParsedExpression
    {
        $expression = trim($expression);

        if ($expression === '') {
            throw new \InvalidArgumentException('Expression cannot be empty.');
        }

        // Extract prefix.key[:hint]
        if (!preg_match('/^([a-z]+)\.([a-zA-Z0-9_]+)(?::([a-zA-Z0-9_]+))?\s+(.+)$/s', $expression, $matches)) {
            throw new \InvalidArgumentException(sprintf('Invalid expression syntax: "%s".', $expression));
        }

        $rawPrefix = $matches[1];
        $key = $matches[2];
        $hint = $matches[3] !== '' ? $matches[3] : null;
        $remainder = trim($matches[4]);

        $prefix = $this->prefixMap[$rawPrefix] ?? null;
        if ($prefix === null) {
            $allowed = implode(', ', array_unique(array_keys($this->prefixMap)));
            throw new \InvalidArgumentException(sprintf('Unknown prefix "%s". Allowed: %s.', $rawPrefix, $allowed));
        }

        // Parse operator and optional placeholder from remainder
        [$operator, $placeholder] = $this->parseOperatorAndPlaceholder($remainder, $expression);

        return new ParsedExpression(
            prefix: $prefix,
            key: $key,
            hint: $hint,
            operator: $operator,
            placeholder: $placeholder,
        );
    }

    /**
     * @return array{string, ?string}
     */
    private function parseOperatorAndPlaceholder(string $remainder, string $originalExpression): array
    {
        // Try two-word operators first (NOT IN, NOT LIKE, etc.)
        foreach (self::VALID_OPERATORS as $op) {
            if (!str_contains($op, ' ')) {
                continue;
            }

            if (str_starts_with(strtoupper($remainder), $op)) {
                $after = trim(substr($remainder, \strlen($op)));

                return $this->resolveOperatorResult($op, $after, $originalExpression);
            }
        }

        // Try single-word operators
        $parts = preg_split('/\s+/', $remainder, 2);
        if ($parts === false || $parts === []) {
            throw new \InvalidArgumentException(sprintf('Invalid expression syntax: "%s".', $originalExpression));
        }

        $operatorCandidate = strtoupper($parts[0]);
        $after = isset($parts[1]) ? trim($parts[1]) : '';

        if (!\in_array($operatorCandidate, self::VALID_OPERATORS, true)) {
            throw new \InvalidArgumentException(sprintf('Unknown operator "%s" in expression: "%s".', $parts[0], $originalExpression));
        }

        return $this->resolveOperatorResult($operatorCandidate, $after, $originalExpression);
    }

    /**
     * @return array{string, ?string}
     */
    private function resolveOperatorResult(string $operator, string $after, string $originalExpression): array
    {
        // EXISTS / NOT EXISTS require no placeholder
        if ($operator === 'EXISTS' || $operator === 'NOT EXISTS') {
            if ($after !== '') {
                throw new \InvalidArgumentException(sprintf('%s operator does not accept a placeholder in expression: "%s".', $operator, $originalExpression));
            }

            return [$operator, null];
        }

        // All other operators require a placeholder
        if ($after === '') {
            throw new \InvalidArgumentException(sprintf('Operator "%s" requires a placeholder in expression: "%s".', $operator, $originalExpression));
        }

        if (!str_starts_with($after, ':')) {
            throw new \InvalidArgumentException(sprintf('Placeholder must start with ":" in expression: "%s".', $originalExpression));
        }

        $placeholder = substr($after, 1);
        if ($placeholder === '' || !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $placeholder)) {
            throw new \InvalidArgumentException(sprintf('Invalid placeholder name in expression: "%s".', $originalExpression));
        }

        return [$operator, $placeholder];
    }
}
