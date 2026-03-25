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

#[AsPanelRenderer(name: 'plugin')]
final class PluginPanelRenderer extends AbstractPanelRenderer implements RendererInterface
{
    public function getName(): string
    {
        return 'plugin';
    }

    public function renderPanel(): string
    {
        $data = $this->getCollectorData();

        return $this->getPhpRenderer()->render('toolbar/panels/plugin', [
            'totalPlugins' => (int) ($data['total_plugins'] ?? 0),
            'totalHookTime' => (float) ($data['total_hook_time'] ?? 0.0),
            'slowestPlugin' => (string) ($data['slowest_plugin'] ?? ''),
            'plugins' => $data['plugins'] ?? [],
            'dropins' => $data['dropins'] ?? [],
            'assetData' => $this->getCollectorData('asset'),
            'fmt' => $this->getFormatters(),
        ]);
    }
}
