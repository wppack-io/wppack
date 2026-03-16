<?php

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

    public function supportsNormalization(mixed $data, ?string $format = null): bool;
}
