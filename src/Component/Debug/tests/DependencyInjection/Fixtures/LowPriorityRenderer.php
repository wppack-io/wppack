<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Tests\DependencyInjection\Fixtures;

use WpPack\Component\Debug\Attribute\AsPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\AbstractPanelRenderer;
use WpPack\Component\Debug\Toolbar\Panel\PanelRendererInterface;

#[AsPanelRenderer(name: 'low', priority: -10)]
final class LowPriorityRenderer extends AbstractPanelRenderer implements PanelRendererInterface
{
    public function getName(): string
    {
        return 'low';
    }

    public function render(\WpPack\Component\Debug\Profiler\Profile $profile): string
    {
        return '';
    }
}
