<?php

declare(strict_types=1);

namespace WpPack\Component\Config\Tests\Fixtures;

use WpPack\Component\Config\Attribute\AsConfig;
use WpPack\Component\Config\Attribute\Env;

#[AsConfig]
final readonly class DefaultConfig
{
    public function __construct(
        #[Env('DEFAULT_HOST')]
        public string $host = 'localhost',
        #[Env('DEFAULT_PORT')]
        public int $port = 3306,
        #[Env('DEFAULT_DEBUG')]
        public bool $debug = false,
        #[Env('DEFAULT_RATE')]
        public float $rate = 1.5,
    ) {}
}
