<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Toolbar\Panel;

use WpPack\Component\Debug\Attribute\AsPanelRenderer;

#[AsPanelRenderer(name: 'wordpress')]
final class WordPressPanelRenderer extends AbstractPanelRenderer implements RendererInterface
{
    public function getName(): string
    {
        return 'wordpress';
    }

    public function renderIndicator(): string
    {
        $wpData = $this->getCollectorData('wordpress');
        return $this->getPhpRenderer()->render('toolbar/panels/wordpress-indicator', [
            'wpVersion' => (string) ($wpData['wp_version'] ?? ''),
            'wpIcon' => ToolbarIcons::svg('wordpress', 18),
        ]);
    }

    public function renderPanel(): string
    {
        return $this->getPhpRenderer()->render('toolbar/panels/wordpress', [
            'wpData' => $this->getCollectorData('wordpress'),
            'themeData' => $this->getCollectorData('theme'),
            'pluginData' => $this->getCollectorData('plugin'),
            'fmt' => $this->getFormatters(),
        ]);
    }
}
