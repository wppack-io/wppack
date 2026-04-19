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

#[AsPanelRenderer(name: 'debug_bar_panel')]
final class DebugBarPanelRenderer extends AbstractPanelRenderer implements RendererInterface
{
    public function getName(): string
    {
        return 'debug_bar_panel';
    }

    public function isEnabled(): bool
    {
        return ($this->getCollectorData()['panel_count'] ?? 0) > 0;
    }

    public function renderPanel(): string
    {
        $data = $this->getCollectorData();

        return $this->getPhpRenderer()->render('toolbar/panels/debug-bar', [
            'panels' => $data['panels'] ?? [],
            'panelCount' => (int) ($data['panel_count'] ?? 0),
            'fmt' => $this->getFormatters(),
        ]);
    }
}
