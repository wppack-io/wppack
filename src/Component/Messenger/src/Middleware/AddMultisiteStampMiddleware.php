<?php

declare(strict_types=1);

namespace WpPack\Component\Messenger\Middleware;

use WpPack\Component\Messenger\Envelope;
use WpPack\Component\Messenger\Stamp\MultisiteStamp;

final class AddMultisiteStampMiddleware implements MiddlewareInterface
{
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        if ($envelope->last(MultisiteStamp::class) === null && function_exists('get_current_blog_id')) {
            $envelope = $envelope->with(new MultisiteStamp(get_current_blog_id()));
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
