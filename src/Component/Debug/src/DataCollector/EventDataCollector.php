<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\DataCollector;

use WpPack\Component\Debug\Attribute\AsDataCollector;

#[AsDataCollector(name: 'event', priority: 85)]
final class EventDataCollector extends AbstractDataCollector
{
    /** @var array<string, int> */
    private array $hookCounts = [];

    private int $totalFirings = 0;

    public function __construct()
    {
        $this->registerHooks();
    }

    public function getName(): string
    {
        return 'event';
    }

    public function getLabel(): string
    {
        return 'Events';
    }

    public function captureHookFired(): void
    {
        if (!function_exists('current_filter')) {
            return;
        }

        $hook = current_filter();
        if (!isset($this->hookCounts[$hook])) {
            $this->hookCounts[$hook] = 0;
        }
        $this->hookCounts[$hook]++;
        $this->totalFirings++;
    }

    public function collect(): void
    {
        // Count listeners from $wp_filter global
        global $wp_filter;

        $registeredHooks = 0;
        $listenerCounts = [];

        if (isset($wp_filter) && is_array($wp_filter)) {
            foreach ($wp_filter as $hookName => $hookObj) {
                if (is_object($hookObj) && isset($hookObj->callbacks)) {
                    $count = 0;
                    $callbacks = $hookObj->callbacks;
                    foreach ($callbacks as $priority => $funcs) {
                        $count += count($funcs);
                    }
                    $listenerCounts[$hookName] = $count;
                    $registeredHooks++;
                }
            }
        }

        // Find orphan hooks (fired with zero registered listeners)
        $orphanCount = 0;
        foreach ($this->hookCounts as $hook => $count) {
            if (!isset($listenerCounts[$hook]) || $listenerCounts[$hook] === 0) {
                $orphanCount++;
            }
        }

        // Top 20 most-fired hooks
        arsort($this->hookCounts);
        $topHooks = array_slice($this->hookCounts, 0, 20, true);

        $this->data = [
            'hooks' => $this->hookCounts,
            'total_firings' => $this->totalFirings,
            'unique_hooks' => count($this->hookCounts),
            'top_hooks' => $topHooks,
            'registered_hooks' => $registeredHooks,
            'orphan_hooks' => $orphanCount,
            'listener_counts' => $listenerCounts,
        ];
    }

    public function getBadgeValue(): string
    {
        return (string) $this->totalFirings;
    }

    public function getBadgeColor(): string
    {
        return match (true) {
            $this->totalFirings < 500 => 'green',
            $this->totalFirings < 1000 => 'yellow',
            default => 'red',
        };
    }

    public function reset(): void
    {
        parent::reset();
        $this->hookCounts = [];
        $this->totalFirings = 0;
    }

    private function registerHooks(): void
    {
        if (!function_exists('add_action')) {
            return;
        }

        add_action('all', [$this, 'captureHookFired']);
    }
}
