<?php

/*
 * This file is part of the WpPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
