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

namespace WPPack\Component\OEmbed;

final class OEmbedProviderDefinition
{
    public function __construct(
        public readonly string $format,
        public readonly string $endpoint,
        public readonly bool $regex = false,
    ) {}
}
