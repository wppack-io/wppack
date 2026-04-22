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

namespace WPPack\Component\Debug\Toolbar;

use WPPack\Component\Debug\DataCollector\DataCollectorInterface;
use WPPack\Component\Debug\DebugConfig;
use WPPack\Component\Debug\Profiler\Profile;

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
    }

    public function onFooter(): void
    {
        if (!$this->config->shouldShowToolbar()) {
            return;
        }

        $code = http_response_code();
        $this->collectProfile(\is_int($code) && $code > 0 ? $code : 200);

        echo $this->renderer->render();
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
