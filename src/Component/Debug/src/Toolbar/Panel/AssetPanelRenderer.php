<?php

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
