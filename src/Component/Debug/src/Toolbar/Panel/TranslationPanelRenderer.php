<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Toolbar\Panel;

use WpPack\Component\Debug\Attribute\AsPanelRenderer;

#[AsPanelRenderer(name: 'translation')]
final class TranslationPanelRenderer extends AbstractPanelRenderer implements RendererInterface
{
    public function getName(): string
    {
        return 'translation';
    }

    public function renderPanel(): string
    {
        $data = $this->getCollectorData();
        $domainUsage = $data['domain_usage'] ?? [];
        arsort($domainUsage);

        return $this->getPhpRenderer()->render('toolbar/panels/translation', [
            'totalLookups' => (int) ($data['total_lookups'] ?? 0),
            'missingCount' => (int) ($data['missing_count'] ?? 0),
            'loadedDomains' => $data['loaded_domains'] ?? [],
            'domainUsage' => $domainUsage,
            'missing' => $data['missing_translations'] ?? [],
        ]);
    }
}
