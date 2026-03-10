<?php

declare(strict_types=1);

namespace WpPack\Component\Query\Wql;

use WpPack\Component\Query\Enum\Order;

final class OrderByParser
{
    private const PREFIX_MAP = [
        'p' => null,
        'post' => null,
        'u' => null,
        'user' => null,
        't' => null,
        'term' => null,
        'm' => 'meta',
        'meta' => 'meta',
    ];

    /**
     * Parse a field expression into a ParsedOrderBy value object.
     *
     * Format: [prefix.]field[:hint]
     *
     * Examples:
     *   date            → standard field (no prefix)
     *   p.date          → standard post field
     *   u.display_name  → standard user field
     *   t.name          → standard term field
     *   m.price:numeric → meta field with type hint
     */
    public function parse(string $field, Order $direction): ParsedOrderBy
    {
        $field = trim($field);

        if ($field === '') {
            throw new \InvalidArgumentException('ORDER BY field cannot be empty.');
        }

        if (!preg_match('/^(?:([a-z]+)\.)?([a-zA-Z0-9_]+)(?::([a-zA-Z0-9_]+))?$/', $field, $matches)) {
            throw new \InvalidArgumentException(sprintf('Invalid ORDER BY syntax: "%s".', $field));
        }

        $rawPrefix = $matches[1] !== '' ? $matches[1] : null;
        $fieldName = $matches[2];
        $hint = $matches[3] ?? null;

        $prefix = null;
        if ($rawPrefix !== null) {
            if (!\array_key_exists($rawPrefix, self::PREFIX_MAP)) {
                throw new \InvalidArgumentException(sprintf(
                    'Unknown prefix "%s". Allowed: p, post, u, user, t, term, m, meta.',
                    $rawPrefix,
                ));
            }
            $prefix = self::PREFIX_MAP[$rawPrefix];
        }

        return new ParsedOrderBy(
            prefix: $prefix,
            field: $fieldName,
            hint: $hint,
            direction: $direction,
        );
    }
}
