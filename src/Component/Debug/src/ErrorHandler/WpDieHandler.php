<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\ErrorHandler;

use WpPack\Component\Debug\DebugConfig;

/**
 * Intercepts wp_die() calls via wp_die_handler / wp_die_ajax_handler / wp_die_json_handler
 * filters and renders them through the Debug ErrorRenderer pipeline.
 */
final class WpDieHandler
{
    /** @var callable|string|null */
    private mixed $previousHtmlHandler = null;

    /** @var callable|string|null */
    private mixed $previousAjaxHandler = null;

    /** @var callable|string|null */
    private mixed $previousJsonHandler = null;

    public function __construct(
        private readonly ErrorRenderer $renderer,
        private readonly DebugConfig $config,
    ) {}

    public function register(): void
    {
        if (!function_exists('add_filter')) {
            return;
        }

        add_filter('wp_die_handler', $this->registerHtmlHandler(...), \PHP_INT_MAX);
        add_filter('wp_die_ajax_handler', $this->registerAjaxHandler(...), \PHP_INT_MAX);
        add_filter('wp_die_json_handler', $this->registerJsonHandler(...), \PHP_INT_MAX);
    }

    /**
     * @param callable|string $handler The previous wp_die handler
     *
     * @return callable The replacement handler
     */
    public function registerHtmlHandler(callable|string $handler): callable
    {
        $this->previousHtmlHandler = $handler;

        return $this->handleHtml(...);
    }

    /**
     * @param callable|string $handler The previous wp_die_ajax handler
     *
     * @return callable The replacement handler
     */
    public function registerAjaxHandler(callable|string $handler): callable
    {
        $this->previousAjaxHandler = $handler;

        return $this->handleAjax(...);
    }

    /**
     * @param callable|string $handler The previous wp_die_json handler
     *
     * @return callable The replacement handler
     */
    public function registerJsonHandler(callable|string $handler): callable
    {
        $this->previousJsonHandler = $handler;

        return $this->handleJson(...);
    }

    /**
     * HTML handler for wp_die() — renders the debug exception page.
     *
     * @param string|\WP_Error $message
     * @param array<string, mixed> $args
     */
    public function handleHtml(string|\WP_Error $message, string $title = '', array $args = []): void
    {
        if (!$this->config->isAccessAllowed()) {
            $this->callPreviousHandler($this->previousHtmlHandler, $message, $title, $args);

            return;
        }

        $exception = $this->createException($message, $title, $args);
        $flat = FlattenException::createFromThrowable($exception);
        $html = $this->renderer->render($flat);

        if (!headers_sent()) {
            http_response_code($exception->getStatusCode());
            header('Content-Type: text/html; charset=UTF-8');
        }

        echo $html;

        if (($args['exit'] ?? true) !== false) {
            exit; // @codeCoverageIgnore
        }
    }

    /**
     * AJAX handler for wp_die() — returns JSON response.
     *
     * @param string|\WP_Error $message
     * @param array<string, mixed> $args
     */
    public function handleAjax(string|\WP_Error $message, string $title = '', array $args = []): void
    {
        if (!$this->config->isAccessAllowed()) {
            $this->callPreviousHandler($this->previousAjaxHandler, $message, $title, $args);

            return;
        }

        $exception = $this->createException($message, $title, $args);

        $this->sendJson([
            'error' => true,
            'message' => $exception->getMessage(),
            'status' => $exception->getStatusCode(),
            'wp_error_codes' => $exception->getWpErrorCodes(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ], $exception->getStatusCode(), $args);
    }

    /**
     * JSON handler for wp_die() — returns JSON response.
     *
     * @param string|\WP_Error $message
     * @param array<string, mixed> $args
     */
    public function handleJson(string|\WP_Error $message, string $title = '', array $args = []): void
    {
        if (!$this->config->isAccessAllowed()) {
            $this->callPreviousHandler($this->previousJsonHandler, $message, $title, $args);

            return;
        }

        $exception = $this->createException($message, $title, $args);

        $this->sendJson([
            'error' => true,
            'message' => $exception->getMessage(),
            'status' => $exception->getStatusCode(),
            'wp_error_codes' => $exception->getWpErrorCodes(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ], $exception->getStatusCode(), $args);
    }

    /**
     * @param string|\WP_Error $message
     * @param array<string, mixed> $args
     */
    private function createException(string|\WP_Error $message, string $title, array $args): WpDieException
    {
        $statusCode = (int) ($args['response'] ?? 500);
        $wpErrorCodes = [];
        $wpErrorData = [];

        if ($message instanceof \WP_Error) {
            $messageText = $message->get_error_message();
            $wpErrorCodes = $message->get_error_codes();
            foreach ($wpErrorCodes as $code) {
                $data = $message->get_error_data($code);
                if ($data !== '') {
                    $wpErrorData[$code] = $data;
                }
            }
        } else {
            $messageText = $message;
        }

        // Strip HTML tags for the exception message
        $messageText = strip_tags($messageText);

        $exception = new WpDieException(
            message: $messageText,
            statusCode: $statusCode,
            wpDieTitle: $title,
            wpDieArgs: $args,
            wpErrorCodes: $wpErrorCodes,
            wpErrorData: $wpErrorData,
        );

        // Override file/line and trace to point to wp_die() call site
        $backtrace = debug_backtrace(0, 20);
        $callSiteIndex = $this->findWpDieCallSiteIndex($backtrace);

        if ($callSiteIndex !== null && isset($backtrace[$callSiteIndex]['file'], $backtrace[$callSiteIndex]['line'])) {
            $this->overrideFileAndLine($exception, $backtrace[$callSiteIndex]['file'], $backtrace[$callSiteIndex]['line']);
            // Inject trace frames starting from the wp_die() call site
            $this->overrideTrace($exception, \array_slice($backtrace, $callSiteIndex));
        }

        return $exception;
    }

    /**
     * @param list<array<string, mixed>> $trace
     */
    private function findWpDieCallSiteIndex(array $trace): ?int
    {
        // Look for wp_die() in the backtrace (real WordPress environment)
        foreach ($trace as $index => $frame) {
            if (($frame['function'] ?? '') === 'wp_die' && isset($frame['file'], $frame['line'])) {
                return $index;
            }
        }

        // Fallback: find handleHtml/handleAjax/handleJson frame.
        // In PHP backtrace, a frame's file/line points to where the function
        // was CALLED FROM, so the handleHtml frame's file/line is the actual
        // call site in user code (equivalent to where wp_die() would be).
        $handlerMethods = ['handleHtml', 'handleAjax', 'handleJson'];
        foreach ($trace as $index => $frame) {
            if (
                isset($frame['file'], $frame['line'])
                && \in_array($frame['function'] ?? '', $handlerMethods, true)
                && ($frame['class'] ?? '') === self::class
            ) {
                return $index;
            }
        }

        return null;
    }

    private function overrideFileAndLine(\Exception $exception, string $file, int $line): void
    {
        try {
            $ref = new \ReflectionProperty(\Exception::class, 'file');
            $ref->setValue($exception, $file);

            $ref = new \ReflectionProperty(\Exception::class, 'line');
            $ref->setValue($exception, $line);
        } catch (\ReflectionException) {
            // Silently ignore if reflection fails
        }
    }

    /**
     * @param list<array<string, mixed>> $trace
     */
    private function overrideTrace(\Exception $exception, array $trace): void
    {
        try {
            $ref = new \ReflectionProperty(\Exception::class, 'trace');
            $ref->setValue($exception, $trace);
        } catch (\ReflectionException) {
            // Silently ignore if reflection fails
        }
    }

    /**
     * @param callable|string|null $handler
     * @param string|\WP_Error $message
     * @param array<string, mixed> $args
     */
    private function callPreviousHandler(callable|string|null $handler, string|\WP_Error $message, string $title, array $args): void
    {
        if ($handler !== null) {
            $handler($message, $title, $args);

            return;
        }

        // Fallback to WordPress default handler
        if (function_exists('_default_wp_die_handler')) {
            _default_wp_die_handler($message, $title, $args);
        }
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $args
     */
    private function sendJson(array $data, int $statusCode, array $args): void
    {
        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: application/json; charset=UTF-8');
        }

        echo json_encode($data, \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);

        if (($args['exit'] ?? true) !== false) {
            exit; // @codeCoverageIgnore
        }
    }
}
