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
