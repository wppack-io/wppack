<?php

declare(strict_types=1);

namespace WpPack\Component\Cache;

use WpPack\Component\Cache\Strategy\KeySplitStrategyInterface;

final readonly class ObjectCacheConfig
{
    /**
     * @param list<KeySplitStrategyInterface> $splitStrategies
     */
    public function __construct(
        public string $prefix = 'wp:',
        public array $splitStrategies = [],
        public ?int $maxTtl = null,
    ) {}
}
