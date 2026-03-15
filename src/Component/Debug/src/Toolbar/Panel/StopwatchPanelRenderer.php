<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Toolbar\Panel;

use WpPack\Component\Debug\Attribute\AsPanelRenderer;

#[AsPanelRenderer(name: 'stopwatch')]
final class StopwatchPanelRenderer extends AbstractPanelRenderer implements RendererInterface
{
    public function getName(): string
    {
        return 'stopwatch';
    }

    public function renderPanel(): string
    {
        $data = $this->getCollectorData();

        return $this->getPhpRenderer()->render('toolbar/panels/stopwatch', [
            'totalTime' => (float) ($data['total_time'] ?? 0.0),
            'events' => $data['events'] ?? [],
            'fmt' => $this->getFormatters(),
        ]);
    }
}
