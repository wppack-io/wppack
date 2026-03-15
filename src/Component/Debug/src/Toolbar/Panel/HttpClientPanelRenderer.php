<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Toolbar\Panel;

use WpPack\Component\Debug\Attribute\AsPanelRenderer;

#[AsPanelRenderer(name: 'http_client')]
final class HttpClientPanelRenderer extends AbstractPanelRenderer implements RendererInterface
{
    public function getName(): string
    {
        return 'http_client';
    }

    public function renderPanel(): string
    {
        $data = $this->getCollectorData();

        return $this->getPhpRenderer()->render('toolbar/panels/http-client', [
            'totalCount' => (int) ($data['total_count'] ?? 0),
            'totalTime' => (float) ($data['total_time'] ?? 0.0),
            'errorCount' => (int) ($data['error_count'] ?? 0),
            'slowCount' => (int) ($data['slow_count'] ?? 0),
            'requests' => $data['requests'] ?? [],
            'fmt' => $this->getFormatters(),
            'requestTimeFloat' => $this->requestTimeFloat,
        ]);
    }
}
