<?php

/*
 * This file is part of the WPPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WPPack\Component\Cache;

use WPPack\Component\Cache\Strategy\HashStrategyInterface;

final readonly class ObjectCacheConfig
{
    /**
     * @param list<HashStrategyInterface> $hashStrategies
     * @param string $serializer Redis-side serializer: 'none', 'php', 'igbinary', 'msgpack'
     */
    public function __construct(
        public string $prefix = 'wp:',
        public array $hashStrategies = [],
        public ?int $maxTtl = null,
        public string $serializer = 'none',
    ) {}
}
