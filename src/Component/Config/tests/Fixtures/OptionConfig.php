<?php

declare(strict_types=1);

namespace WpPack\Component\Config\Tests\Fixtures;

use WpPack\Component\Config\Attribute\AsConfig;
use WpPack\Component\Config\Attribute\Option;

#[AsConfig]
final readonly class OptionConfig
{
    public function __construct(
        #[Option('blogname')]
        public string $siteName = '',
        #[Option('my_plugin_settings.api_endpoint')]
        public string $apiEndpoint = 'https://api.example.com',
        #[Option('my_plugin_settings')]
        public array $allSettings = [],
    ) {}
}
