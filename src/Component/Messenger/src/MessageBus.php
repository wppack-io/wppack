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

namespace WPPack\Component\Messenger;

use WPPack\Component\Messenger\Middleware\MiddlewareInterface;
use WPPack\Component\Messenger\Middleware\MiddlewareStack;

final class MessageBus implements MessageBusInterface
{
    /** @var list<MiddlewareInterface> */
    private readonly array $middlewares;

    /**
     * @param iterable<MiddlewareInterface> $middlewares
     */
    public function __construct(iterable $middlewares = [])
    {
        $this->middlewares = $middlewares instanceof \Traversable
            ? iterator_to_array($middlewares, false)
            : array_values($middlewares);
    }

    public function dispatch(object $message, array $stamps = []): Envelope
    {
        $envelope = Envelope::wrap($message, $stamps);
        $stack = new MiddlewareStack($this->middlewares);

        return $stack->next()->handle($envelope, $stack);
    }
}
