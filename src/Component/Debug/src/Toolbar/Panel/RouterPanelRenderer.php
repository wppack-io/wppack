<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Toolbar\Panel;

use WpPack\Component\Debug\Attribute\AsPanelRenderer;

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
