<?php

declare(strict_types=1);

namespace WpPack\Component\Cache;

final readonly class ObjectCacheMetrics
{
    public function __construct(
        public int $hits,
        public int $misses,
        public ?string $adapterName,
    ) {}
}
