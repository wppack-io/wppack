<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Tests\DependencyInjection\Fixtures;

use WpPack\Component\Debug\Attribute\AsPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\AbstractPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\RendererInterface;

#[AsPanelRenderer(name: 'high', priority: 100)]
final class HighPriorityRenderer extends AbstractPanelRenderer implements RendererInterface
{
    public function getName(): string
    {
        return 'high';
    }

    public function renderPanel(): string
    {
        return '';
    }
}
