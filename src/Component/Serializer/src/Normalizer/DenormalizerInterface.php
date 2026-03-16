<?php

declare(strict_types=1);

namespace WpPack\Component\Serializer\Normalizer;

interface DenormalizerInterface
{
    /**
     * @param class-string         $type
     * @param array<string, mixed> $context
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed;

    /**
     * @param class-string $type
     */
    public function supportsDenormalization(mixed $data, string $type, ?string $format = null): bool;
}
