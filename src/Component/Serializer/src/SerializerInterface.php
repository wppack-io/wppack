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

namespace WpPack\Component\Serializer;

interface SerializerInterface
{
    /**
     * Serialize data to a string in the given format.
     *
     * @param array<string, mixed> $context
     */
    public function serialize(mixed $data, string $format, array $context = []): string;

    /**
     * Deserialize a string back into an object.
     *
     * @param class-string         $type
     * @param array<string, mixed> $context
     */
    public function deserialize(string $data, string $type, string $format, array $context = []): object;

    /**
     * Normalize data into a scalar/array structure.
     *
     * @param array<string, mixed> $context
     *
     * @return array<mixed>|string|int|float|bool|null
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array|string|int|float|bool|null;

    /**
     * Denormalize data back into an object.
     *
     * @param class-string         $type
     * @param array<string, mixed> $context
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): object;
}
