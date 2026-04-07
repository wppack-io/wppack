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

use Psr\Log\LoggerInterface;
use WpPack\Component\Debug\DebugConfig;
use WpPack\Component\Debug\Profiler\Profile;
use WpPack\Component\Debug\Toolbar\ToolbarRenderer;

final class ExceptionHandler
{
    private ?\Closure $previousHandler = null;

    public function __construct(
        private readonly ErrorRenderer $renderer,
        private readonly ?DebugConfig $config = null,
        private readonly ?ToolbarRenderer $toolbarRenderer = null,
        private ?Profile $profile = null,
        private readonly ?LoggerInterface $logger = null,
    ) {}

    public function setProfile(Profile $profile): void
    {
        $this->profile = $profile;
    }

    public function register(): void
    {
        $previous = set_exception_handler($this->handleException(...));
        if ($previous !== null) {
            $this->previousHandler = $previous(...);
        }
    }

    public function handleException(\Throwable $e): void
    {
        if ($this->config !== null && !$this->config->isAccessAllowed()) {
            if ($this->previousHandler !== null) {
                ($this->previousHandler)($e);
                return;
            }
            throw $e;
        }

        // In early mode (no config), clean up output buffers that may
        // contain partial output from the aborted request
        if ($this->config === null) {
            while (ob_get_level()) {
                ob_end_clean();
            }
        }

        // Cancel pending redirect — error page takes priority
        if (isset($GLOBALS['_wppack_redirect_handler']) && $GLOBALS['_wppack_redirect_handler'] instanceof RedirectHandler) {
            $GLOBALS['_wppack_redirect_handler']->cancelPendingRedirect();
        }

        $flat = FlattenException::createFromThrowable($e);

        if (!headers_sent()) {
            http_response_code($flat->getStatusCode());
            header('Content-Type: text/html; charset=UTF-8');
        }

        $toolbarHtml = $this->renderToolbar($flat->getStatusCode());
        echo $this->renderer->render($flat, $toolbarHtml);
    }

    public function onRoutingException(\Throwable $e): void
    {
        $this->handleException($e);
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
