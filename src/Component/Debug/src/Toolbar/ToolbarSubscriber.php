<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Toolbar;

use WpPack\Component\Debug\DataCollector\DataCollectorInterface;
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

        if (!function_exists('add_action')) {
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

        // Collect data from all collectors
        foreach ($this->collectors as $collector) {
            $collector->collect();
            $this->profile->addCollector($collector);
        }

        // Set request info on profile
        $this->profile->setUrl($_SERVER['REQUEST_URI'] ?? '/');
        $this->profile->setMethod($_SERVER['REQUEST_METHOD'] ?? 'GET');

        echo $this->renderer->render();
    }
}
