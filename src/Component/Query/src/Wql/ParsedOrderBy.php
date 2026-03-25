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

use WpPack\Component\Query\Enum\Order;

final readonly class ParsedOrderBy
{
    /**
     * @param ?string $prefix null=standard field, 'meta'=meta field
     */
    public function __construct(
        public ?string $prefix,
        public string $field,
        public ?string $hint,
        public Order $direction,
    ) {}
}
