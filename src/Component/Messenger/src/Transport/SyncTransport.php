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

namespace WpPack\Component\Messenger\Transport;

use WpPack\Component\Messenger\Envelope;
use WpPack\Component\Messenger\Stamp\SentStamp;

final class SyncTransport implements TransportInterface
{
    public function getName(): string
    {
        return 'sync';
    }

    public function send(Envelope $envelope): Envelope
    {
        return $envelope->with(new SentStamp($this->getName()));
    }
}
