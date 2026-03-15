<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Toolbar\Panel;

use WpPack\Component\Debug\Attribute\AsPanelRenderer;

#[AsPanelRenderer(name: 'container')]
final class ContainerPanelRenderer extends AbstractPanelRenderer implements RendererInterface
{
    public function getName(): string
    {
        return 'container';
    }

    public function renderPanel(): string
    {
        $data = $this->getCollectorData();

        return $this->getPhpRenderer()->render('toolbar/panels/container', [
            'serviceCount' => (int) ($data['service_count'] ?? 0),
            'publicCount' => (int) ($data['public_count'] ?? 0),
            'privateCount' => (int) ($data['private_count'] ?? 0),
            'autowiredCount' => (int) ($data['autowired_count'] ?? 0),
            'lazyCount' => (int) ($data['lazy_count'] ?? 0),
            'services' => $data['services'] ?? [],
            'compilerPasses' => $data['compiler_passes'] ?? [],
            'taggedServices' => $data['tagged_services'] ?? [],
            'parameters' => $data['parameters'] ?? [],
            'fmt' => $this->getFormatters(),
        ]);
    }
}
