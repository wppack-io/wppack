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

namespace WpPack\Component\Serializer\Encoder;

interface EncoderInterface
{
    /**
     * @param array<string, mixed> $context
     */
    public function encode(mixed $data, string $format, array $context = []): string;

    /**
     * @param array<string, mixed> $context
     */
    public function supportsEncoding(string $format, array $context = []): bool;
}
