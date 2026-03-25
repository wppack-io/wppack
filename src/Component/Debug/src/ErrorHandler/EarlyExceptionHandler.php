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

namespace WpPack\Component\Debug\ErrorHandler;

/**
 * Lightweight exception handler for the early boot phase.
 *
 * Registered by the fatal-error-handler.php drop-in before the DI container
 * is available. Covers uncaught exceptions thrown during plugin loading,
 * Kernel::boot(), DI container compilation, and autowiring.
 *
 * Once DebugPlugin::boot() runs, ExceptionHandler::register() overwrites
 * set_exception_handler(), keeping this handler as the previous handler.
 */
final class EarlyExceptionHandler
{
    public function __construct(
        private readonly ErrorRenderer $renderer,
    ) {}

    public function register(): void
    {
        set_exception_handler($this->handleException(...));
    }

    public function handleException(\Throwable $e): void
    {
        while (ob_get_level()) {
            ob_end_clean();
        }

        $flat = FlattenException::createFromThrowable($e);

        if (!headers_sent()) {
            http_response_code($flat->getStatusCode());
            header('Content-Type: text/html; charset=UTF-8');
        }

        echo $this->renderer->render($flat);
    }
}
