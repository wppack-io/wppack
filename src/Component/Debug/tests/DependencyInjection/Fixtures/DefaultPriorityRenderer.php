<?php

/*
 * This file is part of the WPPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WPPack\Component\Debug\Tests\DependencyInjection\Fixtures;

use WPPack\Component\Debug\Attribute\AsPanelRenderer;
use WPPack\Component\Debug\Toolbar\Panel\AbstractPanelRenderer;
use WPPack\Component\Debug\Toolbar\Panel\RendererInterface;

#[AsPanelRenderer(name: 'default')]
final class DefaultPriorityRenderer extends AbstractPanelRenderer implements RendererInterface
{
    public function getName(): string
    {
        return 'default';
    }

    public function renderPanel(): string
    {
        return '';
    }
}
