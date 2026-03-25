<?php

/*
 * This file is part of the WpPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WpPack\Component\Messenger\Exception;

use WpPack\Component\Messenger\Envelope;

class HandlerFailedException extends \RuntimeException implements ExceptionInterface
{
    /** @var list<\Throwable> */
    private readonly array $exceptions;

    /**
     * @param list<\Throwable> $exceptions
     */
    public function __construct(
        private readonly Envelope $envelope,
        array $exceptions,
    ) {
        $this->exceptions = $exceptions;

        $message = sprintf(
            'Handling "%s" failed: %d handler(s) threw exceptions.',
            $envelope->getMessage()::class,
            count($exceptions),
        );

        parent::__construct($message, 0, $exceptions[0] ?? null);
    }

    public function getEnvelope(): Envelope
    {
        return $this->envelope;
    }

    /**
     * @return list<\Throwable>
     */
    public function getExceptions(): array
    {
        return $this->exceptions;
    }
}
