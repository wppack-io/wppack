<?php

declare(strict_types=1);

namespace WpPack\Component\Messenger\Serializer;

use WpPack\Component\Messenger\Envelope;

interface SerializerInterface
{
    /**
     * @return array{headers: array<string, mixed>, body: string}
     */
    public function encode(Envelope $envelope): array;

    /**
     * @param array{headers: array<string, mixed>, body: string} $data
     */
    public function decode(array $data): Envelope;
}
