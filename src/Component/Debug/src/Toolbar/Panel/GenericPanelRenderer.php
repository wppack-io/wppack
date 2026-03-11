<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Toolbar\Panel;

final class GenericPanelRenderer extends AbstractPanelRenderer implements PanelRendererInterface
{
    public function getName(): string
    {
        return 'generic';
    }

    public function render(array $data): string
    {
        if ($data === []) {
            return '<div class="wpd-section"><p class="wpd-text-dim">No data collected.</p></div>';
        }

        return $this->renderKeyValueSection('Data', $data);
    }
}
