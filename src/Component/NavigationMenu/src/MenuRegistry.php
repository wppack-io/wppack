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

namespace WPPack\Component\NavigationMenu;

final class MenuRegistry
{
    /** @var list<MenuLocationProviderInterface> */
    private array $providers = [];

    /** @var array<string, string> */
    private array $locations = [];

    public function addProvider(MenuLocationProviderInterface $provider): void
    {
        $this->providers[] = $provider;
    }

    /**
     * Register all collected locations via register_nav_menus().
     *
     * Intended to be called on the after_setup_theme hook.
     */
    public function register(): void
    {
        foreach ($this->providers as $provider) {
            foreach ($provider->getMenuLocations() as $location => $description) {
                $this->locations[$location] = $description;
            }
        }

        if ($this->locations !== []) {
            register_nav_menus($this->locations);
        }
    }

    public function registerLocation(string $location, string $description): void
    {
        $this->locations[$location] = $description;
        register_nav_menus([$location => $description]);
    }

    /**
     * @param array<string, string> $locations
     */
    public function registerLocations(array $locations): void
    {
        foreach ($locations as $location => $description) {
            $this->locations[$location] = $description;
        }

        register_nav_menus($locations);
    }

    public function unregisterLocation(string $location): void
    {
        unset($this->locations[$location]);
        unregister_nav_menu($location);
    }

    public function hasLocation(string $location): bool
    {
        return isset($this->locations[$location]);
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->locations;
    }
}
