<?php

declare(strict_types=1);

namespace WpPack\Component\Messenger\Middleware;

use Psr\Log\LoggerInterface;
use WpPack\Component\Messenger\Envelope;
use WpPack\Component\Messenger\Exception\HandlerFailedException;
use WpPack\Component\Messenger\Exception\NoHandlerForMessageException;
use WpPack\Component\Messenger\Handler\HandlerLocatorInterface;
use WpPack\Component\Messenger\Stamp\HandledStamp;

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
