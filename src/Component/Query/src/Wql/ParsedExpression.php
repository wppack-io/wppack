<?php

declare(strict_types=1);

namespace WpPack\Component\Query\Wql;

final readonly class ParsedExpression implements ExpressionNode
{
    /**
     * @param 'meta'|'tax' $prefix
     */
    public function __construct(
        public string $prefix,
        public string $key,
        public ?string $hint,
        public string $operator,
        public ?string $placeholder,
    ) {}
}
