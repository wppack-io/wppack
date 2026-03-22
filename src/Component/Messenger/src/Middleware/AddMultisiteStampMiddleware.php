<?php

declare(strict_types=1);

namespace WpPack\Component\Messenger\Middleware;

use WpPack\Component\Messenger\Envelope;
use WpPack\Component\Messenger\Stamp\MultisiteStamp;
use WpPack\Component\Site\BlogContext;
use WpPack\Component\Site\BlogContextInterface;

final class AddMultisiteStampMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly BlogContextInterface $blogContext = new BlogContext(),
    ) {}

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        if ($envelope->last(MultisiteStamp::class) === null) {
            $envelope = $envelope->with(new MultisiteStamp($this->blogContext->getCurrentBlogId()));
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
