<?php

declare(strict_types=1);

namespace WpPack\Component\Cache;

use WpPack\Component\Cache\Strategy\HashStrategyInterface;

final readonly class ObjectCacheConfig
{
    /**
     * @param list<HashStrategyInterface> $hashStrategies
     */
    public function __construct(
        public string $prefix = 'wp:',
        public array $hashStrategies = [],
        public ?int $maxTtl = null,
    ) {}
}
