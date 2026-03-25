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

namespace WpPack\Component\Serializer\Normalizer;

interface NormalizerInterface
{
    /**
     * @param array<string, mixed> $context
     *
     * @return array<mixed>|string|int|float|bool|null
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array|string|int|float|bool|null;

    /**
     * @param array<string, mixed> $context
     */
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool;
}
