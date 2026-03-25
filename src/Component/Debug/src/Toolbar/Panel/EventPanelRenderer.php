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

namespace WpPack\Component\Debug\Toolbar\Panel;

use WpPack\Component\Debug\Attribute\AsPanelRenderer;

#[AsPanelRenderer(name: 'event')]
final class EventPanelRenderer extends AbstractPanelRenderer implements RendererInterface
{
    public function getName(): string
    {
        return 'event';
    }

    public function renderPanel(): string
    {
        $data = $this->getCollectorData();

        return $this->getPhpRenderer()->render('toolbar/panels/event', [
            'totalFirings' => (int) ($data['total_firings'] ?? 0),
            'uniqueHooks' => (int) ($data['unique_hooks'] ?? 0),
            'registeredHooks' => (int) ($data['registered_hooks'] ?? 0),
            'orphanHooks' => (int) ($data['orphan_hooks'] ?? 0),
            'topHooks' => $data['top_hooks'] ?? [],
            'hookTimings' => $data['hook_timings'] ?? [],
            'listenerCounts' => $data['listener_counts'] ?? [],
            'componentSummary' => $data['component_summary'] ?? [],
            'fmt' => $this->getFormatters(),
        ]);
    }
}
