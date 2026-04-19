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

#[AsPanelRenderer(name: 'wp_error')]
final class WpErrorPanelRenderer extends AbstractPanelRenderer implements RendererInterface
{
    public function getName(): string
    {
        return 'wp_error';
    }

    public function renderPanel(): string
    {
        $data = $this->getCollectorData();

        return $this->getPhpRenderer()->render('toolbar/panels/wp_error', [
            'totalCount' => (int) ($data['total_count'] ?? 0),
            'uniqueObjects' => (int) ($data['unique_objects'] ?? 0),
            'errors' => $data['errors'] ?? [],
            'fmt' => $this->getFormatters(),
            'requestTimeFloat' => $this->requestTimeFloat,
        ]);
    }
}
