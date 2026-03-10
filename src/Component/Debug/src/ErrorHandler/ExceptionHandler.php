<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\ErrorHandler;

use WpPack\Component\Debug\DebugConfig;

final class ExceptionHandler
{
    private ?\Closure $previousHandler = null;

    public function __construct(
        private readonly ErrorRenderer $renderer,
        private readonly DebugConfig $config,
    ) {}

    public function register(): void
    {
        $previous = set_exception_handler($this->handleException(...));
        if ($previous !== null) {
            $this->previousHandler = $previous(...);
        }
    }

    public function handleException(\Throwable $e): void
    {
        if (!$this->config->isEnabled()) {
            if ($this->previousHandler !== null) {
                ($this->previousHandler)($e);
                return;
            }
            throw $e;
        }

        $flat = FlattenException::createFromThrowable($e);
        $html = $this->renderer->render($flat);

        if (!headers_sent()) {
            http_response_code($flat->getStatusCode());
            header('Content-Type: text/html; charset=UTF-8');
        }

        echo $html;
    }

    public function onRoutingException(\Throwable $e): void
    {
        $this->handleException($e);
    }
}
