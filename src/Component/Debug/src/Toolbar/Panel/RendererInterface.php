<?php

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
