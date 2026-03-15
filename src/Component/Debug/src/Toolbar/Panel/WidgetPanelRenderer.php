<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Toolbar\Panel;

use WpPack\Component\Debug\Attribute\AsPanelRenderer;

#[AsPanelRenderer(name: 'widget')]
final class WidgetPanelRenderer extends AbstractPanelRenderer implements RendererInterface
{
    public function getName(): string
    {
        return 'widget';
    }

    public function renderPanel(): string
    {
        $data = $this->getCollectorData();

        return $this->getPhpRenderer()->render('toolbar/panels/widget', [
            'totalWidgets' => (int) ($data['total_widgets'] ?? 0),
            'totalSidebars' => (int) ($data['total_sidebars'] ?? 0),
            'activeWidgets' => (int) ($data['active_widgets'] ?? 0),
            'renderTime' => (float) ($data['render_time'] ?? 0.0),
            'sidebars' => $data['sidebars'] ?? [],
            'sidebarTimings' => $data['sidebar_timings'] ?? [],
            'fmt' => $this->getFormatters(),
        ]);
    }
}
