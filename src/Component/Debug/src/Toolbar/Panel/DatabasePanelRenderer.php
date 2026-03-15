<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Toolbar\Panel;

use WpPack\Component\Debug\Attribute\AsPanelRenderer;

#[AsPanelRenderer(name: 'database')]
final class DatabasePanelRenderer extends AbstractPanelRenderer implements RendererInterface
{
    public function getName(): string
    {
        return 'database';
    }

    public function renderPanel(): string
    {
        $data = $this->getCollectorData();

        return $this->getPhpRenderer()->render('toolbar/panels/database', [
            'totalCount' => (int) ($data['total_count'] ?? 0),
            'totalTime' => (float) ($data['total_time'] ?? 0.0),
            'duplicateCount' => (int) ($data['duplicate_count'] ?? 0),
            'slowCount' => (int) ($data['slow_count'] ?? 0),
            'suggestions' => $data['suggestions'] ?? [],
            'queries' => $data['queries'] ?? [],
            'fmt' => $this->getFormatters(),
            'requestTimeFloat' => $this->requestTimeFloat,
        ]);
    }
}
