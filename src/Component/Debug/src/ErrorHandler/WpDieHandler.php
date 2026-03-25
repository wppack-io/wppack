<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\ErrorHandler;

use Psr\Log\LoggerInterface;
use WpPack\Component\Debug\DebugConfig;
use WpPack\Component\Debug\Profiler\Profile;
use WpPack\Component\Debug\Toolbar\ToolbarRenderer;

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
        private readonly ?ToolbarRenderer $toolbarRenderer = null,
        private ?Profile $profile = null,
        private readonly ?LoggerInterface $logger = null,
        private readonly ?WpErrorOriginCapture $wpErrorOriginCapture = null,
    ) {}

    public function setProfile(Profile $profile): void
    {
        $this->profile = $profile;
    }

    public function register(): void
    {
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

        // Set HTTP status before rendering — rendering may trigger PHP
        // warnings that leak to output, which would make headers_sent() true.
        if (!headers_sent()) {
            status_header($exception->getStatusCode());
            header('Content-Type: text/html; charset=UTF-8');
        }

        $flat = FlattenException::createFromThrowable($exception);
        $toolbarHtml = $this->renderToolbar((int) ($args['response'] ?? 500));
        $html = $this->renderer->render($flat, $toolbarHtml);

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
            'wp_error_codes' => $this->extractWpErrorCodes($exception),
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
            'wp_error_codes' => $this->extractWpErrorCodes($exception),
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
        $previous = null;
        /** @var array{file: string, line: int, args: list<mixed>}|null $wpErrorOrigin */
        $wpErrorOrigin = null;

        if ($message instanceof \WP_Error) {
            $messageText = $message->get_error_message();
            $wpErrorCodes = $message->get_error_codes();
            $wpErrorData = [];
            foreach ($wpErrorCodes as $code) {
                $data = $message->get_error_data($code);
                if ($data !== '') {
                    $wpErrorData[$code] = $data;
                }
            }
            $previous = new WpErrorException(
                message: $messageText,
                wpErrorCodes: $wpErrorCodes,
                wpErrorData: $wpErrorData,
            );
            $wpErrorOrigin = $this->wpErrorOriginCapture?->get($message);
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
            previous: $previous,
        );

        // Override file/line and trace to point to wp_die() call site
        $backtrace = debug_backtrace(0, 20);
        $callSiteIndex = $this->findWpDieCallSiteIndex($backtrace);

        if ($callSiteIndex !== null && isset($backtrace[$callSiteIndex]['file'], $backtrace[$callSiteIndex]['line'])) {
            $callSiteFile = $backtrace[$callSiteIndex]['file'];
            $callSiteLine = $backtrace[$callSiteIndex]['line'];
            $callSiteTrace = \array_slice($backtrace, $callSiteIndex);

            $this->overrideFileAndLine($exception, $callSiteFile, $callSiteLine);
            $this->overrideTrace($exception, $callSiteTrace);

            // Point the WpErrorException to where "new WP_Error" was created
            // (captured via wp_error_added hook), falling back to the wp_die call site
            if ($previous !== null) {
                $errorFile = $wpErrorOrigin['file'] ?? $callSiteFile;
                $errorLine = $wpErrorOrigin['line'] ?? $callSiteLine;
                $this->overrideFileAndLine($previous, $errorFile, $errorLine);

                // Build a trace with the WP_Error::__construct call so the
                // Previous Exceptions section shows constructor arguments
                $errorTrace = $wpErrorOrigin !== null ? [[
                    'function' => '__construct',
                    'class' => \WP_Error::class,
                    'type' => '->',
                    'file' => $errorFile,
                    'line' => $errorLine,
                    'args' => $wpErrorOrigin['args'],
                ]] : [];
                $this->overrideTrace($previous, $errorTrace);
            }
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
        } catch (\ReflectionException $e) {
            $this->logger?->debug('Failed to override exception file/line: {message}', [
                'message' => $e->getMessage(),
            ]);
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
        } catch (\ReflectionException $e) {
            $this->logger?->debug('Failed to override exception trace: {message}', [
                'message' => $e->getMessage(),
            ]);
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
        _default_wp_die_handler($message, $title, $args);
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

    /**
     * @return list<string>
     */
    private function extractWpErrorCodes(WpDieException $exception): array
    {
        $previous = $exception->getPrevious();

        if ($previous instanceof WpErrorException) {
            return $previous->getWpErrorCodes();
        }

        return [];
    }

    private function renderToolbar(int $statusCode = 500): string
    {
        if ($this->toolbarRenderer === null || $this->profile === null) {
            return '';
        }

        foreach ($this->profile->getCollectors() as $collector) {
            try {
                $collector->collect();
            } catch (\Throwable $e) {
                $this->logger?->warning('Data collector "{collector}" failed during toolbar rendering: {message}', [
                    'collector' => $collector->getName(),
                    'message' => $e->getMessage(),
                    'exception' => $e,
                ]);
            }
        }

        $this->profile->setUrl($_SERVER['REQUEST_URI'] ?? '/');
        $this->profile->setMethod($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $this->profile->setStatusCode($statusCode);

        return $this->toolbarRenderer->render();
    }
}
