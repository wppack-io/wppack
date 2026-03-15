<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Toolbar\Panel;

use WpPack\Component\Debug\Attribute\AsPanelRenderer;

#[AsPanelRenderer(name: 'rest')]
final class RestPanelRenderer extends AbstractPanelRenderer implements RendererInterface
{
    public function getName(): string
    {
        return 'rest';
    }

    public function renderPanel(): string
    {
        $data = $this->getCollectorData();

        return $this->getPhpRenderer()->render('toolbar/panels/rest', [
            'isRestRequest' => (bool) ($data['is_rest_request'] ?? false),
            'currentRequest' => $data['current_request'] ?? null,
            'totalRoutes' => (int) ($data['total_routes'] ?? 0),
            'totalNamespaces' => (int) ($data['total_namespaces'] ?? 0),
            'routes' => $data['routes'] ?? [],
            'fmt' => $this->getFormatters(),
        ]);
    }
}
