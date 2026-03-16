<?php

declare(strict_types=1);

namespace WpPack\Component\Messenger\Middleware;

use WpPack\Component\Messenger\Envelope;
use WpPack\Component\Messenger\Stamp\BusNameStamp;

final class AddBusNameStampMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly string $busName = 'default',
    ) {}

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        if ($envelope->last(BusNameStamp::class) === null) {
            $envelope = $envelope->with(new BusNameStamp($this->busName));
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
