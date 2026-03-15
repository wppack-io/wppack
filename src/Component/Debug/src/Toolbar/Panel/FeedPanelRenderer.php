<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Toolbar\Panel;

use WpPack\Component\Debug\Attribute\AsPanelRenderer;

#[AsPanelRenderer(name: 'feed')]
final class FeedPanelRenderer extends AbstractPanelRenderer implements RendererInterface
{
    public function getName(): string
    {
        return 'feed';
    }

    public function renderPanel(): string
    {
        $data = $this->getCollectorData();

        return $this->getPhpRenderer()->render('toolbar/panels/feed', [
            'totalCount' => (int) ($data['total_count'] ?? 0),
            'customCount' => (int) ($data['custom_count'] ?? 0),
            'feedDiscovery' => $data['feed_discovery'] ?? true,
            'feeds' => $data['feeds'] ?? [],
            'fmt' => $this->getFormatters(),
        ]);
    }
}
