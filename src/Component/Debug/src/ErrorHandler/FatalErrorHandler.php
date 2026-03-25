<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\ErrorHandler;

final class FatalErrorHandler
{
    private const FATAL_ERROR_TYPES = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR;

    public function __construct(
        private readonly ErrorRenderer $renderer,
    ) {}

    public function handle(): void
    {
        $error = error_get_last();
        if ($error === null || !($error['type'] & self::FATAL_ERROR_TYPES)) {
            return;
        }

        while (ob_get_level()) {
            ob_end_clean();
        }

        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
        }

        $exception = new \ErrorException(
            $error['message'],
            0,
            $error['type'],
            $error['file'],
            $error['line'],
        );
        $flat = FlattenException::createFromThrowable($exception);

        echo $this->renderer->render($flat);
    }
}
