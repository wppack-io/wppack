<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Toolbar\Panel;

use WpPack\Component\Debug\Attribute\AsPanelRenderer;

#[AsPanelRenderer(name: 'request')]
final class RequestPanelRenderer extends AbstractPanelRenderer implements RendererInterface
{
    public function getName(): string
    {
        return 'request';
    }

    public function renderPanel(): string
    {
        return $this->getPhpRenderer()->render('toolbar/panels/request', [
            'data' => $this->getCollectorData(),
            'fmt' => $this->getFormatters(),
        ]);
    }
}
