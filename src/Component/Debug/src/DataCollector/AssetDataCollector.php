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

#[AsDataCollector(name: 'asset', priority: 120)]
final class AssetDataCollector extends AbstractDataCollector
{
    public function getName(): string
    {
        return 'asset';
    }

    public function getLabel(): string
    {
        return 'Assets';
    }

    public function collect(): void
    {
        global $wp_scripts, $wp_styles;

        $scripts = [];
        $styles = [];
        $enqueuedScripts = 0;
        $enqueuedStyles = 0;
        $registeredScripts = 0;
        $registeredStyles = 0;

        if ($wp_scripts instanceof \WP_Scripts) {
            $registeredScripts = count($wp_scripts->registered);
            foreach ($wp_scripts->registered as $handle => $dep) {
                $enqueued = in_array($handle, $wp_scripts->queue, true);
                if ($enqueued) {
                    $enqueuedScripts++;
                }
                $scripts[$handle] = [
                    'handle' => $handle,
                    'src' => $dep->src ?: '',
                    'version' => $dep->ver ?: '',
                    'in_footer' => isset($dep->extra['group']) && $dep->extra['group'] === 1,
                    'deps' => $dep->deps,
                    'enqueued' => $enqueued,
                ];
            }
        }

        if ($wp_styles instanceof \WP_Styles) {
            $registeredStyles = count($wp_styles->registered);
            foreach ($wp_styles->registered as $handle => $dep) {
                $enqueued = in_array($handle, $wp_styles->queue, true);
                if ($enqueued) {
                    $enqueuedStyles++;
                }
                $styles[$handle] = [
                    'handle' => $handle,
                    'src' => $dep->src ?: '',
                    'version' => $dep->ver ?: '',
                    'media' => $dep->args ?: 'all',
                    'deps' => $dep->deps,
                    'enqueued' => $enqueued,
                ];
            }
        }

        $this->data = [
            'scripts' => $scripts,
            'styles' => $styles,
            'enqueued_scripts' => $enqueuedScripts,
            'enqueued_styles' => $enqueuedStyles,
            'registered_scripts' => $registeredScripts,
            'registered_styles' => $registeredStyles,
        ];
    }

    public function getIndicatorValue(): string
    {
        $total = ($this->data['enqueued_scripts'] ?? 0) + ($this->data['enqueued_styles'] ?? 0);

        return $total > 0 ? (string) $total : '';
    }

    public function getIndicatorColor(): string
    {
        return 'default';
    }
}
