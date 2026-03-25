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

namespace WpPack\Component\Debug\Toolbar;

use WpPack\Component\Debug\CssTheme;
use WpPack\Component\Debug\DataCollector\DataCollectorInterface;
use WpPack\Component\Debug\DataCollector\RequestDataCollector;
use WpPack\Component\Debug\DebugConfig;
use WpPack\Component\Debug\Profiler\Profile;

final class ToolbarSubscriber
{
    /**
     * @param iterable<DataCollectorInterface> $collectors
     */
    public function __construct(
        private readonly DebugConfig $config,
        private readonly ToolbarRenderer $renderer,
        private readonly Profile $profile,
        private readonly iterable $collectors,
    ) {}

    public function register(): void
    {
        if (!$this->config->shouldShowToolbar()) {
            return;
        }

        add_action('wp_footer', $this->onFooter(...), 9999);
        add_action('admin_footer', $this->onFooter(...), 9999);
        add_filter('wp_redirect', $this->onRedirect(...), \PHP_INT_MAX, 2);
    }

    public function onFooter(): void
    {
        if (!$this->config->shouldShowToolbar()) {
            return;
        }

        $this->collectProfile(http_response_code() ?: 200);

        echo $this->renderer->render();
    }

    /**
     * Intercept redirects to show a toolbar-equipped intermediate page.
     *
     * This allows inspecting WP_Error and other collected data that would
     * otherwise be lost during POST → redirect → GET flows.
     */
    public function onRedirect(string $location, int $status): string
    {
        if (!$this->config->shouldShowToolbar()) {
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

        $this->collectProfile($status);

        $toolbarHtml = $this->renderer->render();

        // Clear any existing output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }

        http_response_code(200);

        $phpRenderer = $this->renderer->getPhpRenderer();
        echo $phpRenderer->render('redirect', [
            'location' => $location,
            'status' => $status,
            'requestMethod' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
            'requestUri' => $_SERVER['REQUEST_URI'] ?? '/',
            'toolbarHtml' => $toolbarHtml,
            'cssVariables' => CssTheme::cssVariables(),
        ]);

        exit;
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
