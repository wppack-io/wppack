<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Tests\DependencyInjection\Fixtures;

use WpPack\Component\Debug\Attribute\AsPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\AbstractPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\PanelRendererInterface;

#[AsPanelRenderer(name: 'high', priority: 100)]
final class HighPriorityRenderer extends AbstractPanelRenderer implements PanelRendererInterface
{
    public function getName(): string
    {
        return 'high';
    }

    public function render(array $data): string
    {
        return '';
    }
}
