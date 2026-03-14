<?php

declare(strict_types=1);

namespace WpPack\Component\Logger;

use WpPack\Component\Logger\ChannelResolver\ChannelResolverInterface;

final class ErrorHandler
{
    private bool $registered = false;

    private bool $handling = false;

    private mixed $previousErrorHandler = null;

    public function __construct(
        private readonly LoggerFactory $loggerFactory,
        private readonly ChannelResolverInterface $channelResolver,
        private readonly bool $captureAllErrors = true,
    ) {}

    public function register(): void
    {
        if ($this->registered) {
            return;
        }

        $this->previousErrorHandler = set_error_handler($this->handleError(...));
        $this->registered = true;
    }

    public function restore(): void
    {
        if (!$this->registered) {
            return;
        }

        restore_error_handler();
        $this->previousErrorHandler = null;
        $this->registered = false;
    }

    private function handleError(int $errno, string $errstr, string $errfile, int $errline): bool
    {
        // captureAllErrors=false: Respect error_reporting() mask (@ suppression + global setting)
        // captureAllErrors=true:  Capture all PHP errors regardless of error_reporting()
        if (!$this->captureAllErrors && !(error_reporting() & $errno)) {
            return false;
        }

        // Prevent re-entrant calls
        if ($this->handling) {
            return false;
        }

        $this->handling = true;

        try {
            $level = match ($errno) {
                \E_DEPRECATED, \E_USER_DEPRECATED => 'notice',
                \E_NOTICE, \E_USER_NOTICE => 'notice',
                \E_WARNING, \E_USER_WARNING => 'warning',
                \E_RECOVERABLE_ERROR => 'error',
                default => 'warning',
            };

            $errorType = match ($errno) {
                \E_DEPRECATED => 'E_DEPRECATED',
                \E_USER_DEPRECATED => 'E_USER_DEPRECATED',
                \E_NOTICE => 'E_NOTICE',
                \E_USER_NOTICE => 'E_USER_NOTICE',
                \E_WARNING => 'E_WARNING',
                \E_USER_WARNING => 'E_USER_WARNING',
                \E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
                default => 'E_UNKNOWN',
            };

            $context = [
                '_file' => $errfile,
                '_line' => $errline,
                '_error_type' => $errorType,
            ];

            if ($errno === \E_DEPRECATED || $errno === \E_USER_DEPRECATED) {
                $context['_type'] = 'deprecation';
            }

            $channel = $this->channelResolver->resolve($errfile);
            $this->loggerFactory->create($channel)->log($level, $errstr, $context);
        } finally {
            $this->handling = false;
        }

        // Call previous handler if any
        if ($this->previousErrorHandler !== null) {
            ($this->previousErrorHandler)($errno, $errstr, $errfile, $errline);
        }

        // Logger handles the error — stop PHP's built-in handler
        return true;
    }
}
