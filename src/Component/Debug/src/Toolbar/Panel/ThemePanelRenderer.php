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

#[AsPanelRenderer(name: 'theme')]
final class ThemePanelRenderer extends AbstractPanelRenderer implements RendererInterface
{
    public function getName(): string
    {
        return 'theme';
    }

    public function renderPanel(): string
    {
        return $this->getPhpRenderer()->render('toolbar/panels/theme', [
            'data' => $this->getCollectorData(),
            'assetData' => $this->getCollectorData('asset'),
            'fmt' => $this->getFormatters(),
        ]);
    }
}
