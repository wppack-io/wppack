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

namespace WPPack\Component\Query\Wql;

final readonly class ParsedExpression implements ExpressionNode
{
    /**
     * @param 'meta'|'tax'|'post'|'user'|'term' $prefix
     */
    public function __construct(
        public string $prefix,
        public string $key,
        public ?string $hint,
        public string $operator,
        public ?string $placeholder,
    ) {}
}
