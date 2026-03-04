<?php

declare(strict_types=1);

namespace WpPack\Component\NavigationMenu;

interface MenuLocationProviderInterface
{
    /**
     * @return array<string, string> location => description
     */
    public function getMenuLocations(): array;
}
