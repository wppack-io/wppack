<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Toolbar\Panel;

use WpPack\Component\Debug\Attribute\AsPanelRenderer;

#[AsPanelRenderer(name: 'shortcode')]
final class ShortcodePanelRenderer extends AbstractPanelRenderer implements RendererInterface
{
    public function getName(): string
    {
        return 'shortcode';
    }

    public function renderPanel(): string
    {
        $data = $this->getCollectorData();

        return $this->getPhpRenderer()->render('toolbar/panels/shortcode', [
            'totalCount' => (int) ($data['total_count'] ?? 0),
            'usedCount' => (int) ($data['used_count'] ?? 0),
            'executionTime' => (float) ($data['execution_time'] ?? 0.0),
            'usedShortcodes' => $data['used_shortcodes'] ?? [],
            'shortcodes' => $data['shortcodes'] ?? [],
            'executions' => $data['executions'] ?? [],
            'fmt' => $this->getFormatters(),
        ]);
    }
}
