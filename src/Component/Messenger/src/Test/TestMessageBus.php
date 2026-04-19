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

namespace WPPack\Component\Messenger\Test;

use WPPack\Component\Messenger\Envelope;
use WPPack\Component\Messenger\MessageBusInterface;
use WPPack\Component\Messenger\Stamp\StampInterface;

final class TestMessageBus implements MessageBusInterface
{
    /** @var list<Envelope> */
    private array $dispatched = [];

    /**
     * @param array<StampInterface> $stamps
     */
    public function dispatch(object $message, array $stamps = []): Envelope
    {
        $envelope = Envelope::wrap($message, $stamps);
        $this->dispatched[] = $envelope;

        return $envelope;
    }

    /**
     * @return list<Envelope>
     */
    public function getDispatched(): array
    {
        return $this->dispatched;
    }

    /**
     * @return list<object>
     */
    public function getDispatchedMessages(): array
    {
        return array_map(
            static fn(Envelope $envelope): object => $envelope->getMessage(),
            $this->dispatched,
        );
    }

    public function reset(): void
    {
        $this->dispatched = [];
    }
}
