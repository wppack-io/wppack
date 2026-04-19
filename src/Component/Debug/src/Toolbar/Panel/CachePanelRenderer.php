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

namespace WPPack\Component\Debug\Toolbar\Panel;

use WPPack\Component\Debug\Attribute\AsPanelRenderer;

#[AsPanelRenderer(name: 'cache')]
final class CachePanelRenderer extends AbstractPanelRenderer implements RendererInterface
{
    public function getName(): string
    {
        return 'cache';
    }

    public function renderPanel(): string
    {
        $data = $this->getCollectorData();

        return $this->getPhpRenderer()->render('toolbar/panels/cache', [
            'hits' => (int) ($data['hits'] ?? 0),
            'misses' => (int) ($data['misses'] ?? 0),
            'hitRate' => (float) ($data['hit_rate'] ?? 0.0),
            'transientSets' => (int) ($data['transient_sets'] ?? 0),
            'transientDeletes' => (int) ($data['transient_deletes'] ?? 0),
            'dropin' => (string) ($data['object_cache_dropin'] ?? ''),
            'transientOps' => $data['transient_operations'] ?? [],
            'cacheGroups' => $data['cache_groups'] ?? [],
            'fmt' => $this->getFormatters(),
        ]);
    }
}
