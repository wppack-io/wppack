<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Toolbar\Panel;

interface PanelRendererInterface
{
    public function getName(): string;

    /**
     * @param array<string, mixed> $data
     */
    public function render(array $data): string;
}
