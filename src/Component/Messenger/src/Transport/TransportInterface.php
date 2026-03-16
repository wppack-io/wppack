<?php

declare(strict_types=1);

namespace WpPack\Component\Messenger\Transport;

use WpPack\Component\Messenger\Envelope;

interface TransportInterface
{
    public function getName(): string;

    public function send(Envelope $envelope): Envelope;
}
