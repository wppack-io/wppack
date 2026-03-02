<?php

declare(strict_types=1);

namespace WpPack\Component\Config\Tests\Fixtures;

use WpPack\Component\Config\Attribute\AsConfig;
use WpPack\Component\Config\Attribute\Env;

#[AsConfig]
final readonly class UnionTypeConfig
{
    public function __construct(
        #[Env('UNION_VALUE')]
        public string|int $value,
    ) {}
}
