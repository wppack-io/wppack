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

#[AsPanelRenderer(name: 'router')]
final class RouterPanelRenderer extends AbstractPanelRenderer implements RendererInterface
{
    public function getName(): string
    {
        return 'router';
    }

    public function renderPanel(): string
    {
        return $this->getPhpRenderer()->render('toolbar/panels/router', [
            'data' => $this->getCollectorData(),
            'fmt' => $this->getFormatters(),
        ]);
    }
}
