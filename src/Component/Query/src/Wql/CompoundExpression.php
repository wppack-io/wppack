<?php

declare(strict_types=1);

namespace WpPack\Component\Query\Wql;

final readonly class CompoundExpression implements ExpressionNode
{
    /**
     * @param 'AND'|'OR' $operator
     * @param list<ExpressionNode> $children
     */
    public function __construct(
        public string $operator,
        public array $children,
    ) {}
}
