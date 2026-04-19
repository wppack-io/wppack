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

use Psr\Log\LoggerInterface;
use WPPack\Component\Messenger\Envelope;
use WPPack\Component\Messenger\Exception\HandlerFailedException;
use WPPack\Component\Messenger\Exception\NoHandlerForMessageException;
use WPPack\Component\Messenger\Handler\HandlerLocatorInterface;
use WPPack\Component\Messenger\Stamp\HandledStamp;

final class HandleMessageMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly HandlerLocatorInterface $handlerLocator,
        private readonly bool $allowNoHandlers = false,
        private readonly ?LoggerInterface $logger = null,
    ) {}

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $message = $envelope->getMessage();
        $exceptions = [];

        $handlers = $this->handlerLocator->getHandlers($message);
        $handled = false;

        foreach ($handlers as $handlerDescriptor) {
            $handled = true;
            $handler = $handlerDescriptor->getHandler();
            $handlerName = $handlerDescriptor->getName();

            try {
                $result = $handler($message);
                $envelope = $envelope->with(new HandledStamp($result, $handlerName));

                $this->logger?->info('Message {class} handled by {handler}', [
                    'class' => $message::class,
                    'handler' => $handlerName,
                ]);
            } catch (\Throwable $e) {
                $exceptions[] = $e;

                $this->logger?->error('Message {class} failed in handler {handler}: {error}', [
                    'class' => $message::class,
                    'handler' => $handlerName,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($exceptions !== []) {
            throw new HandlerFailedException($envelope, $exceptions);
        }

        if (!$handled && !$this->allowNoHandlers) {
            throw new NoHandlerForMessageException(sprintf(
                'No handler for message "%s".',
                $message::class,
            ));
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
