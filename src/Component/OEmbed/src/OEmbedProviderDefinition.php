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

namespace WpPack\Component\OEmbed;

final class OEmbedProviderDefinition
{
    public function __construct(
        public readonly string $format,
        public readonly string $endpoint,
        public readonly bool $regex = false,
    ) {}
}
