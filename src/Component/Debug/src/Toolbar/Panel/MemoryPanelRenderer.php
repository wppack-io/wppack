<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Toolbar\Panel;

use WpPack\Component\Debug\Attribute\AsPanelRenderer;

#[AsPanelRenderer(name: 'memory')]
final class MemoryPanelRenderer extends AbstractPanelRenderer implements RendererInterface
{
    public function getName(): string
    {
        return 'memory';
    }

    public function renderPanel(): string
    {
        $data = $this->getCollectorData();

        return $this->getPhpRenderer()->render('toolbar/panels/memory', [
            'current' => (int) ($data['current'] ?? 0),
            'peak' => (int) ($data['peak'] ?? 0),
            'limit' => (int) ($data['limit'] ?? 0),
            'usagePercentage' => (float) ($data['usage_percentage'] ?? 0.0),
            'snapshots' => $data['snapshots'] ?? [],
            'fmt' => $this->getFormatters(),
        ]);
    }
}
