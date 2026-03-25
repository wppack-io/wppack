<?php

/*
 * This file is part of the WpPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
