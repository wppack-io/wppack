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

#[AsPanelRenderer(name: 'asset')]
final class AssetPanelRenderer extends AbstractPanelRenderer implements RendererInterface
{
    public function getName(): string
    {
        return 'asset';
    }

    public function renderPanel(): string
    {
        $data = $this->getCollectorData();

        return $this->getPhpRenderer()->render('toolbar/panels/asset', [
            'enqueuedScripts' => (int) ($data['enqueued_scripts'] ?? 0),
            'enqueuedStyles' => (int) ($data['enqueued_styles'] ?? 0),
            'registeredScripts' => (int) ($data['registered_scripts'] ?? 0),
            'registeredStyles' => (int) ($data['registered_styles'] ?? 0),
            'scripts' => $data['scripts'] ?? [],
            'styles' => $data['styles'] ?? [],
            'fmt' => $this->getFormatters(),
        ]);
    }
}
