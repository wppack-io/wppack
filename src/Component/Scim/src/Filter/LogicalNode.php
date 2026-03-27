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

final readonly class LogicalNode implements FilterNode
{
    /**
     * @param string $operator "and" | "or"
     */
    public function __construct(
        public string $operator,
        public FilterNode $left,
        public FilterNode $right,
    ) {}
}
