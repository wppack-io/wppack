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

namespace WpPack\Component\Debug\Toolbar\Panel;

interface RendererInterface
{
    public function getName(): string;

    /**
     * Whether this panel should be visible in the toolbar.
     */
    public function isEnabled(): bool;

    public function renderPanel(): string;

    public function renderIndicator(): string;
}
