<?php

declare(strict_types=1);

namespace WpPack\Component\Serializer\Encoder;

interface DecoderInterface
{
    /**
     * @param array<string, mixed> $context
     */
    public function decode(string $data, string $format, array $context = []): mixed;

    public function supportsDecoding(string $format): bool;
}
