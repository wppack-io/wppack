<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\ErrorHandler;

use WpPack\Component\Debug\DebugConfig;
use WpPack\Component\Debug\Profiler\Profile;
use WpPack\Component\Debug\Toolbar\ToolbarRenderer;

final class ExceptionHandler
{
    private ?\Closure $previousHandler = null;

    public function __construct(
        private readonly ErrorRenderer $renderer,
        private readonly DebugConfig $config,
        private readonly ?ToolbarRenderer $toolbarRenderer = null,
        private ?Profile $profile = null,
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
        if (!$this->config->isAccessAllowed()) {
            if ($this->previousHandler !== null) {
                ($this->previousHandler)($e);
                return;
            }
            throw $e;
        }

        $flat = FlattenException::createFromThrowable($e);
        $toolbarHtml = $this->renderToolbar();
        $html = $this->renderer->render($flat, $toolbarHtml);

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

    private function renderToolbar(): string
    {
        if ($this->toolbarRenderer === null || $this->profile === null) {
            return '';
        }

        return $this->toolbarRenderer->render($this->profile);
    }
}
