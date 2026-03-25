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

namespace WpPack\Component\Wpress\ContentProcessor;

final class PlainContentProcessor implements ContentProcessorInterface
{
    public function decode(string $data): string
    {
        return $data;
    }

    public function encode(string $data): string
    {
        return $data;
    }
}
