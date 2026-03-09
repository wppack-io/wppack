<?php

declare(strict_types=1);

namespace WpPack\Component\Query\Wql;

final readonly class Token
{
    public function __construct(
        public TokenType $type,
        public string $value,
    ) {}
}
