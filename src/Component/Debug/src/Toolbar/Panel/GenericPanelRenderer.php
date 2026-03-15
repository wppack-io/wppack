<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Toolbar\Panel;

final class GenericPanelRenderer extends AbstractPanelRenderer implements RendererInterface
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

    public function renderPanel(): string
    {
        $data = $this->getCollectorData($this->collectorName);

        return $this->getPhpRenderer()->render('toolbar/panels/generic', [
            'data' => $data,
            'fmt' => $this->getFormatters(),
        ]);
    }
}
