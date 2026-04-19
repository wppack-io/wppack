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

final readonly class ObjectCacheMetrics
{
    public function __construct(
        public int $hits,
        public int $misses,
        public ?string $adapterName,
    ) {}
}
