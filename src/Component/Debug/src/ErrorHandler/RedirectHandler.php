<?php

/*
 * This file is part of the WPPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WPPack\Component\Debug\ErrorHandler;

use WPPack\Component\Debug\CssTheme;
use WPPack\Component\Debug\DataCollector\DataCollectorInterface;
use WPPack\Component\Debug\DataCollector\RequestDataCollector;
use WPPack\Component\Debug\DebugConfig;
use WPPack\Component\Debug\Profiler\Profile;
use WPPack\Component\Debug\Toolbar\ToolbarRenderer;

/**
 * Intercepts redirects to show a toolbar-equipped intermediate page.
 *
 * This allows inspecting WP_Error and other collected data that would
 * otherwise be lost during POST → redirect → GET flows.
 *
 * Used in both the early boot phase (drop-in, without DI) and the full
 * boot phase (with DI container). When DI dependencies are null, the
 * handler operates in a lightweight mode without toolbar or profiling.
 */
final class RedirectHandler
{
    /** @var array{location: string, status: int}|null */
    private ?array $pendingRedirect = null;

    private bool $shutdownRegistered = false;
    private bool $errorOccurred = false;

    private ?\Closure $callback = null;

    /**
     * @param iterable<DataCollectorInterface> $collectors
     */
    public function __construct(
        private readonly ErrorRenderer $errorRenderer,
        private readonly ?DebugConfig $config = null,
        private readonly ?ToolbarRenderer $toolbarRenderer = null,
        private readonly ?Profile $profile = null,
        private readonly iterable $collectors = [],
    ) {}

    public function register(): void
    {
        if (isset($GLOBALS['_wppack_redirect_handler'])) {
            $GLOBALS['_wppack_redirect_handler']->unregister();
        }

        if ($this->config !== null && !$this->config->shouldShowToolbar()) {
            unset($GLOBALS['_wppack_redirect_handler']);
            return;
        }

        $this->callback = $this->onRedirect(...);
        add_filter('wp_redirect', $this->callback, \PHP_INT_MAX, 2);
        $GLOBALS['_wppack_redirect_handler'] = $this;
    }

    /**
     * Signal that an error/exception page has been rendered.
     * Prevents the redirect page from also rendering on shutdown.
     */
    public function cancelPendingRedirect(): void
    {
        $this->errorOccurred = true;
        $this->pendingRedirect = null;
    }

    public function unregister(): void
    {
        if ($this->callback !== null) {
            remove_filter('wp_redirect', $this->callback, \PHP_INT_MAX);
            $this->callback = null;
        }
    }

    /**
     * Intercept redirects to show a toolbar-equipped intermediate page.
     *
     * We must NOT call exit() inside this filter. WordPress calls
     * wp_redirect() even when post-redirect processing still needs to
     * complete (e.g. plugin activation writes the active_plugins option
     * after the redirect call). Calling exit here would abort that
     * processing and cause fatal-error-like failures.
     *
     * Instead we store the redirect data and register a single shutdown
     * function that renders the intermediate page once WordPress has
     * finished. When wp_redirect() is called multiple times, only the
     * last redirect is shown.
     *
     * @return string Empty string to cancel the redirect
     */
    public function onRedirect(string $location, int $status): string
    {
        if ($this->config !== null && !$this->config->shouldShowToolbar()) {
            return $location;
        }

        // Inject redirect status into RequestDataCollector before collect(),
        // because status_header has not fired yet at this point
        foreach ($this->collectors as $collector) {
            if ($collector instanceof RequestDataCollector) {
                $collector->captureStatusCode('', $status);
                break;
            }
        }

        if ($this->profile !== null) {
            $this->collectProfile($status);
        }

        // Always overwrite — only the last redirect matters
        $this->pendingRedirect = [
            'location' => $location,
            'status' => $status,
        ];

        // Skip the shutdown-registered intermediate page in CLI contexts
        // (phpunit, WP-CLI). register_shutdown_function() cannot be
        // unregistered, so once a test triggers onRedirect() the shutdown
        // callback lives until the PHP process exits and then calls
        // `while (ob_get_level()) ob_end_clean()`, which eats phpunit's
        // test-summary output and makes the run appear to terminate
        // silently mid-stream. HTML rendering belongs to the browser
        // path, never to a CLI binary.
        if (!$this->shutdownRegistered && \PHP_SAPI !== 'cli') {
            $this->shutdownRegistered = true;
            register_shutdown_function($this->renderRedirectPage(...));
            ob_start();
        }

        // Return empty to cancel wp_redirect()'s Location header
        return '';
    }

    private function renderRedirectPage(): void
    {
        if ($this->pendingRedirect === null || $this->errorOccurred) {
            return;
        }

        while (ob_get_level()) {
            ob_end_clean();
        }

        if (!headers_sent()) {
            header_remove('Location');
            http_response_code(200);
        }

        $phpRenderer = $this->toolbarRenderer?->getPhpRenderer()
            ?? $this->errorRenderer->getPhpRenderer();
        echo $phpRenderer->render('redirect', [
            'location' => $this->sanitizeRedirectUrl($this->pendingRedirect['location']),
            'status' => $this->pendingRedirect['status'],
            'requestMethod' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'requestUri' => $_SERVER['REQUEST_URI'] ?? '/',
            'toolbarHtml' => $this->toolbarRenderer?->render() ?? '',
            'cssVariables' => CssTheme::cssVariables(),
        ]);
    }

    /**
     * Reject non-HTTP(S) schemes to prevent javascript: URLs in href attributes.
     */
    private function sanitizeRedirectUrl(string $url): string
    {
        $scheme = parse_url($url, \PHP_URL_SCHEME);
        if (\is_string($scheme) && !\in_array(strtolower($scheme), ['http', 'https'], true)) {
            return '';
        }

        return $url;
    }

    private function collectProfile(int $statusCode): void
    {
        foreach ($this->collectors as $collector) {
            $collector->collect();
            $this->profile->addCollector($collector);
        }

        $this->profile->setUrl($_SERVER['REQUEST_URI'] ?? '/');
        $this->profile->setMethod($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $this->profile->setStatusCode($statusCode);
    }
}
