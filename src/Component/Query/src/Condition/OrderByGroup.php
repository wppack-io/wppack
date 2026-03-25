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

namespace WpPack\Component\Query\Condition;

use WpPack\Component\Query\Enum\Order;
use WpPack\Component\Query\Wql\OrderByParser;
use WpPack\Component\Query\Wql\ParsedOrderBy;

final class OrderByGroup
{
    private static ?OrderByParser $parser = null;

    /** @var list<ParsedOrderBy> */
    private array $clauses = [];

    private static function parser(): OrderByParser
    {
        return self::$parser ??= new OrderByParser();
    }

    public function set(string $field, Order $direction): void
    {
        $this->clauses = [self::parser()->parse($field, $direction)];
    }

    public function add(string $field, Order $direction): void
    {
        $this->clauses[] = self::parser()->parse($field, $direction);
    }

    public function isEmpty(): bool
    {
        return $this->clauses === [];
    }

    /**
     * @param array<string, mixed> $metaQuery Existing meta_query (named clauses may be injected)
     * @return array<string, mixed> Merge-ready args (orderby, order, meta_key, meta_type)
     */
    public function toArgs(array &$metaQuery): array
    {
        if ($this->clauses === []) {
            return [];
        }

        // Single clause — use simple format
        if (\count($this->clauses) === 1) {
            $clause = $this->clauses[0];

            if ($clause->prefix === 'meta') {
                return $this->buildSingleMetaArgs($clause);
            }

            return [
                'orderby' => $clause->field,
                'order' => $clause->direction->value,
            ];
        }

        // Multiple clauses — use array format
        $orderby = [];

        foreach ($this->clauses as $clause) {
            if ($clause->prefix === 'meta') {
                $clauseName = '__wppack_ob_' . $clause->field;
                $metaClause = [
                    'key' => $clause->field,
                    'compare' => 'EXISTS',
                ];
                if ($clause->hint !== null) {
                    $metaClause['type'] = strtoupper($clause->hint);
                }
                $metaQuery[$clauseName] = $metaClause;
                $orderby[$clauseName] = $clause->direction->value;
            } else {
                $orderby[$clause->field] = $clause->direction->value;
            }
        }

        return ['orderby' => $orderby];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSingleMetaArgs(ParsedOrderBy $clause): array
    {
        $args = [
            'meta_key' => $clause->field,
            'orderby' => 'meta_value',
            'order' => $clause->direction->value,
        ];

        if ($clause->hint !== null) {
            $type = strtoupper($clause->hint);
            if (\in_array($type, ['NUMERIC', 'DECIMAL', 'SIGNED', 'UNSIGNED'], true)) {
                $args['orderby'] = 'meta_value_num';
            }
            $args['meta_type'] = $type;
        }

        return $args;
    }
}
