<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Tests\DependencyInjection\Fixtures;

use WpPack\Component\Debug\Attribute\AsPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\AbstractPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\RendererInterface;

#[AsPanelRenderer(name: 'low', priority: -10)]
final class LowPriorityRenderer extends AbstractPanelRenderer implements RendererInterface
{
    public function getName(): string
    {
        return 'low';
    }

    public function renderPanel(): string
    {
        return '';
    }
}
