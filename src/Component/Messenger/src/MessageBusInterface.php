<?php

declare(strict_types=1);

namespace WpPack\Component\Messenger;

use WpPack\Component\Messenger\Stamp\StampInterface;

interface MessageBusInterface
{
    /**
     * @param array<StampInterface> $stamps
     */
    public function dispatch(object $message, array $stamps = []): Envelope;
}
