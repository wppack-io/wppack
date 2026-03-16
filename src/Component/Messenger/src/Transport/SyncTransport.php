<?php

declare(strict_types=1);

namespace WpPack\Component\Messenger\Transport;

use WpPack\Component\Messenger\Envelope;

final class SyncTransport implements TransportInterface
{
    public function getName(): string
    {
        return 'sync';
    }

    public function send(Envelope $envelope): Envelope
    {
        return $envelope;
    }
}
