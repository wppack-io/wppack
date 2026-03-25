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

interface DecoderInterface
{
    /**
     * @param array<string, mixed> $context
     */
    public function decode(string $data, string $format, array $context = []): mixed;

    /**
     * @param array<string, mixed> $context
     */
    public function supportsDecoding(string $format, array $context = []): bool;
}
