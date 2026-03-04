<?php

declare(strict_types=1);

namespace WpPack\Component\NavigationMenu\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\NavigationMenu\MenuLocationProviderInterface;
use WpPack\Component\NavigationMenu\MenuRegistry;

final class MenuRegistryTest extends TestCase
{
    private MenuRegistry $registry;

    protected function setUp(): void
    {
        if (!function_exists('register_nav_menus')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $this->registry = new MenuRegistry();
    }

    #[Test]
    public function registerCollectsLocationsFromProviders(): void
    {
        $provider = new class implements MenuLocationProviderInterface {
            public function getMenuLocations(): array
            {
                return [
                    'primary' => 'Primary Menu',
                    'footer' => 'Footer Menu',
                ];
            }
        };

        $this->registry->addProvider($provider);
        $this->registry->register();

        self::assertTrue($this->registry->hasLocation('primary'));
        self::assertTrue($this->registry->hasLocation('footer'));
    }

    #[Test]
    public function registerMergesMultipleProviders(): void
    {
        $provider1 = new class implements MenuLocationProviderInterface {
            public function getMenuLocations(): array
            {
                return ['primary' => 'Primary Menu'];
            }
        };

        $provider2 = new class implements MenuLocationProviderInterface {
            public function getMenuLocations(): array
            {
                return ['footer' => 'Footer Menu'];
            }
        };

        $this->registry->addProvider($provider1);
        $this->registry->addProvider($provider2);
        $this->registry->register();

        self::assertSame([
            'primary' => 'Primary Menu',
            'footer' => 'Footer Menu',
        ], $this->registry->getRegisteredLocations());
    }

    #[Test]
    public function registerLocationAddsSingleLocation(): void
    {
        $this->registry->registerLocation('sidebar', 'Sidebar Menu');

        self::assertTrue($this->registry->hasLocation('sidebar'));
        self::assertSame('Sidebar Menu', $this->registry->getRegisteredLocations()['sidebar']);
    }

    #[Test]
    public function registerLocationsAddsMultipleLocations(): void
    {
        $this->registry->registerLocations([
            'primary' => 'Primary Menu',
            'footer' => 'Footer Menu',
        ]);

        self::assertTrue($this->registry->hasLocation('primary'));
        self::assertTrue($this->registry->hasLocation('footer'));
    }

    #[Test]
    public function unregisterLocationRemovesLocation(): void
    {
        $this->registry->registerLocation('primary', 'Primary Menu');
        $this->registry->unregisterLocation('primary');

        self::assertFalse($this->registry->hasLocation('primary'));
    }

    #[Test]
    public function hasLocationReturnsFalseForUnknownLocation(): void
    {
        self::assertFalse($this->registry->hasLocation('nonexistent'));
    }

    #[Test]
    public function getRegisteredLocationsReturnsEmptyArrayByDefault(): void
    {
        self::assertSame([], $this->registry->getRegisteredLocations());
    }

    #[Test]
    public function laterProviderOverridesEarlierProviderLocation(): void
    {
        $provider1 = new class implements MenuLocationProviderInterface {
            public function getMenuLocations(): array
            {
                return ['primary' => 'Primary Menu V1'];
            }
        };

        $provider2 = new class implements MenuLocationProviderInterface {
            public function getMenuLocations(): array
            {
                return ['primary' => 'Primary Menu V2'];
            }
        };

        $this->registry->addProvider($provider1);
        $this->registry->addProvider($provider2);
        $this->registry->register();

        self::assertSame('Primary Menu V2', $this->registry->getRegisteredLocations()['primary']);
    }
}
