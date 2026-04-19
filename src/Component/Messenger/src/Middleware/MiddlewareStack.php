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

namespace WPPack\Component\Messenger\Middleware;

use WPPack\Component\Messenger\Envelope;

final class MiddlewareStack implements StackInterface
{
    /** @var list<MiddlewareInterface> */
    private array $middlewares;
    private int $offset = 0;

    /**
     * @param iterable<MiddlewareInterface> $middlewares
     */
    public function __construct(iterable $middlewares)
    {
        $this->middlewares = $middlewares instanceof \Traversable
            ? iterator_to_array($middlewares, false)
            : array_values($middlewares);
    }

    public function next(): MiddlewareInterface
    {
        if (!isset($this->middlewares[$this->offset])) {
            return new class implements MiddlewareInterface {
                public function handle(Envelope $envelope, StackInterface $stack): Envelope
                {
                    return $envelope;
                }
            };
        }

        return $this->middlewares[$this->offset++];
    }
}
