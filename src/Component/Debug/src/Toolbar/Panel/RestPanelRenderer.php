<?php

/*
 * This file is part of the WPPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WPPack\Component\Debug\Toolbar\Panel;

use WPPack\Component\Debug\Attribute\AsPanelRenderer;

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
