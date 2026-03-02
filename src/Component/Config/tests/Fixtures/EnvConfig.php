<?php

declare(strict_types=1);

namespace WpPack\Component\Config\Tests\Fixtures;

use WpPack\Component\Config\Attribute\AsConfig;
use WpPack\Component\Config\Attribute\Env;

#[AsConfig]
final readonly class EnvConfig
{
    public function __construct(
        #[Env('APP_NAME')]
        public string $appName,
        #[Env('APP_PORT')]
        public int $port = 8080,
    ) {}
}
