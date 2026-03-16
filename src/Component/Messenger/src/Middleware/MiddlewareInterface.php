<?php

declare(strict_types=1);

namespace WpPack\Component\Messenger\Middleware;

use WpPack\Component\Messenger\Envelope;

interface MiddlewareInterface
{
    public function handle(Envelope $envelope, StackInterface $stack): Envelope;
}
