<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Toolbar\Panel;

interface RendererInterface
{
    public function getName(): string;

    public function renderPanel(): string;

    public function renderIndicator(): string;
}
