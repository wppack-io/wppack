<?php

declare(strict_types=1);

namespace WpPack\Component\Messenger\Middleware;

use WpPack\Component\Messenger\Envelope;
use WpPack\Component\Messenger\Stamp\ReceivedStamp;
use WpPack\Component\Messenger\Stamp\SentStamp;
use WpPack\Component\Messenger\Stamp\TransportStamp;
use WpPack\Component\Messenger\Transport\SyncTransport;
use WpPack\Component\Messenger\Transport\TransportInterface;

final class SendMessageMiddleware implements MiddlewareInterface
{
    /** @var array<string, TransportInterface> */
    private readonly array $transports;

    /**
     * @param array<string, TransportInterface> $transports keyed by name
     */
    public function __construct(array $transports = [])
    {
        $this->transports = $transports;
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        // Already received from a transport -- skip sending, proceed to handling
        if ($envelope->last(ReceivedStamp::class) !== null) {
            return $stack->next()->handle($envelope, $stack);
        }

        // Already sent -- skip
        if ($envelope->last(SentStamp::class) !== null) {
            return $stack->next()->handle($envelope, $stack);
        }

        // Determine which transport to use
        $transportStamp = $envelope->last(TransportStamp::class);
        $transportName = $transportStamp?->transportName;

        if ($transportName !== null && isset($this->transports[$transportName])) {
            $transport = $this->transports[$transportName];
            $envelope = $transport->send($envelope);
            $envelope = $envelope->with(new SentStamp($transport->getName()));

            // If not sync, don't proceed to handler (handler runs in the consumer/Lambda)
            if (!$transport instanceof SyncTransport) {
                return $envelope;
            }
        }

        // No transport or sync transport -- continue to handler
        return $stack->next()->handle($envelope, $stack);
    }
}
