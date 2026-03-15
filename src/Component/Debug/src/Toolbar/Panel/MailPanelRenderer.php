<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Toolbar\Panel;

use WpPack\Component\Debug\Attribute\AsPanelRenderer;

#[AsPanelRenderer(name: 'mail')]
final class MailPanelRenderer extends AbstractPanelRenderer implements RendererInterface
{
    public function getName(): string
    {
        return 'mail';
    }

    public function renderPanel(): string
    {
        $data = $this->getCollectorData();

        return $this->getPhpRenderer()->render('toolbar/panels/mail', [
            'totalCount' => (int) ($data['total_count'] ?? 0),
            'successCount' => (int) ($data['success_count'] ?? 0),
            'failureCount' => (int) ($data['failure_count'] ?? 0),
            'emails' => $data['emails'] ?? [],
            'fmt' => $this->getFormatters(),
        ]);
    }
}
