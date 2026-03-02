<?php

declare(strict_types=1);

namespace WpPack\Component\Kernel\Tests\Fixtures;

use WpPack\Component\DependencyInjection\Container;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\Kernel\PluginInterface;

class AnotherPlugin implements PluginInterface
{
    public bool $registered = false;
    public bool $booted = false;

    public function getPluginFile(): string
    {
        return __FILE__;
    }

    public function register(ContainerBuilder $builder): void
    {
        $this->registered = true;

        $builder->register(AnotherService::class, AnotherService::class)
            ->setPublic(true);
    }

    public function getCompilerPasses(): array
    {
        return [];
    }

    public function boot(Container $container): void
    {
        $this->booted = true;
    }

    public function onActivate(): void {}

    public function onDeactivate(): void {}
}
