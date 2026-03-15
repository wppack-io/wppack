<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Toolbar\Panel;

use WpPack\Component\Debug\Attribute\AsPanelRenderer;

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
