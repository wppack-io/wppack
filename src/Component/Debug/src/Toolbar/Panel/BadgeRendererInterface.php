<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Toolbar\Panel;

use WpPack\Component\Debug\Profiler\Profile;

interface BadgeRendererInterface
{
    public function renderBadge(Profile $profile): string;
}
