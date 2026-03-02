<?php

declare(strict_types=1);

namespace WpPack\Component\Config\Tests\Fixtures;

use WpPack\Component\Config\Attribute\AsConfig;
use WpPack\Component\Config\Attribute\Constant;
use WpPack\Component\Config\Attribute\Env;

#[AsConfig]
final readonly class MixedConfig
{
    public function __construct(
        #[Env('MIXED_API_KEY')]
        public string $apiKey,
        #[Constant('MIXED_DEBUG')]
        public bool $debug = false,
    ) {}
}
