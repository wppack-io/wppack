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

namespace WpPack\Component\Debug\DataCollector;

use WpPack\Component\Debug\Attribute\AsDataCollector;

#[AsDataCollector(name: 'ajax', priority: 130)]
final class AjaxDataCollector extends AbstractDataCollector
{
    public function getName(): string
    {
        return 'ajax';
    }

    public function getLabel(): string
    {
        return 'Ajax';
    }

    public function collect(): void
    {
        global $wp_filter;

        if (!is_array($wp_filter)) {
            $this->data = [
                'registered_actions' => [],
                'total_actions' => 0,
                'nopriv_count' => 0,
            ];

            return;
        }

        $actions = [];
        $noprivCount = 0;
        $noprivActions = [];

        // Collect wp_ajax_nopriv_* actions first
        foreach ($wp_filter as $hookName => $hookObj) {
            if (!str_starts_with($hookName, 'wp_ajax_nopriv_')) {
                continue;
            }

            $action = substr($hookName, 15); // strlen('wp_ajax_nopriv_')
            $noprivActions[$action] = true;
        }

        // Collect wp_ajax_* actions
        foreach ($wp_filter as $hookName => $hookObj) {
            if (!str_starts_with($hookName, 'wp_ajax_')) {
                continue;
            }
            if (str_starts_with($hookName, 'wp_ajax_nopriv_')) {
                continue;
            }

            $action = substr($hookName, 8); // strlen('wp_ajax_')
            $callback = $this->extractCallback($hookObj);
            $hasNopriv = isset($noprivActions[$action]);

            $actions[$action] = [
                'callback' => $callback,
                'nopriv' => $hasNopriv,
            ];

            if ($hasNopriv) {
                $noprivCount++;
            }
        }

        // Add nopriv-only actions
        foreach ($noprivActions as $action => $true) {
            if (!isset($actions[$action])) {
                $hookObj = $wp_filter['wp_ajax_nopriv_' . $action] ?? null;
                $callback = $this->extractCallback($hookObj);
                $actions[$action] = [
                    'callback' => $callback,
                    'nopriv' => true,
                ];
                $noprivCount++;
            }
        }

        ksort($actions);

        $this->data = [
            'registered_actions' => $actions,
            'total_actions' => count($actions),
            'nopriv_count' => $noprivCount,
        ];
    }

    public function getIndicatorValue(): string
    {
        return '0';
    }

    public function getIndicatorColor(): string
    {
        return 'default';
    }

    private function extractCallback(mixed $hookObj): string
    {
        if (!is_object($hookObj) || !isset($hookObj->callbacks)) {
            return 'unknown';
        }

        foreach ($hookObj->callbacks as $priority => $funcs) {
            foreach ($funcs as $func) {
                return $this->formatCallback($func['function'] ?? null);
            }
        }

        return 'unknown';
    }

    private function formatCallback(mixed $callback): string
    {
        if (is_string($callback)) {
            return $callback;
        }

        if (is_array($callback) && count($callback) === 2) {
            $class = is_object($callback[0]) ? $callback[0]::class : (string) $callback[0];

            return $class . '::' . (string) $callback[1];
        }

        if ($callback instanceof \Closure) {
            return 'Closure';
        }

        return 'unknown';
    }
}
