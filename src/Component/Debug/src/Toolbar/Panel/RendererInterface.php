<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Toolbar\Panel;

use WpPack\Component\Debug\Profiler\Profile;

interface RendererInterface
{
    public function getName(): string;

    public function renderPanel(Profile $profile): string;

    public function renderBadge(Profile $profile): string;
}
