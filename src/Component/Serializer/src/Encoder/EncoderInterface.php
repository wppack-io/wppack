<?php

declare(strict_types=1);

namespace WpPack\Component\Serializer\Encoder;

interface EncoderInterface
{
    /**
     * @param array<string, mixed> $context
     */
    public function encode(mixed $data, string $format, array $context = []): string;

    public function supportsEncoding(string $format): bool;
}
