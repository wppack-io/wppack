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

#[AsPanelRenderer(name: 'logger')]
final class LoggerPanelRenderer extends AbstractPanelRenderer implements RendererInterface
{
    public function getName(): string
    {
        return 'logger';
    }

    public function renderPanel(): string
    {
        $data = $this->getCollectorData();
        $levelCounts = $data['level_counts'] ?? [];

        return $this->getPhpRenderer()->render('toolbar/panels/logger', [
            'totalCount' => (int) ($data['total_count'] ?? 0),
            'errorCount' => (int) ($data['error_count'] ?? 0),
            'deprecationCount' => (int) ($data['deprecation_count'] ?? 0),
            'warningCount' => (int) ($levelCounts['warning'] ?? 0),
            'channelCounts' => $data['channel_counts'] ?? [],
            'logs' => $data['logs'] ?? [],
            'fmt' => $this->getFormatters(),
            'requestTimeFloat' => $this->requestTimeFloat,
        ]);
    }
}
