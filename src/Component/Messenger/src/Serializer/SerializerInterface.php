<?php

/*
 * This file is part of the WPPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WPPack\Component\Messenger\Serializer;

use WPPack\Component\Messenger\Envelope;

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
