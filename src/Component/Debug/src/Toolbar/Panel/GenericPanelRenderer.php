<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Toolbar\Panel;

use WpPack\Component\Debug\Profiler\Profile;

final class GenericPanelRenderer extends AbstractPanelRenderer implements PanelRendererInterface
{
    private string $collectorName = '';

    public function setCollectorName(string $name): void
    {
        $this->collectorName = $name;
    }

    public function getName(): string
    {
        return 'generic';
    }

    public function render(Profile $profile): string
    {
        $data = $this->getCollectorData($profile, $this->collectorName);

        if ($data === []) {
            return '<div class="wpd-section"><p class="wpd-text-dim">No data collected.</p></div>';
        }

        return $this->renderKeyValueSection('Data', $data);
    }
}
