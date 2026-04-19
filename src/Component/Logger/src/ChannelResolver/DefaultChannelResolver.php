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

namespace WPPack\Component\Logger\ChannelResolver;

final class DefaultChannelResolver implements ChannelResolverInterface
{
    public function __construct(
        private readonly string $defaultChannel = 'php',
    ) {}

    public function resolve(string $filePath): string
    {
        return $this->defaultChannel;
    }
}
