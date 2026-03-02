<?php

declare(strict_types=1);

namespace WpPack\Component\Kernel\Tests\Fixtures;

use WpPack\Component\DependencyInjection\Container;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\Kernel\PluginInterface;

class TestPlugin implements PluginInterface
{
    public bool $registered = false;
    public bool $booted = false;
    public ?Container $bootedContainer = null;

    /** @var list<\WpPack\Component\DependencyInjection\Compiler\CompilerPassInterface> */
    private array $compilerPasses;

    /**
     * @param list<\WpPack\Component\DependencyInjection\Compiler\CompilerPassInterface> $compilerPasses
     */
    public function __construct(array $compilerPasses = [])
    {
        $this->compilerPasses = $compilerPasses;
    }

    public function getPluginFile(): string
    {
        return __FILE__;
    }

    public function register(ContainerBuilder $builder): void
    {
        $this->registered = true;

        $builder->register(TestService::class, TestService::class)
            ->setPublic(true);
    }

    public function getCompilerPasses(): array
    {
        return $this->compilerPasses;
    }

    public function boot(Container $container): void
    {
        $this->booted = true;
        $this->bootedContainer = $container;
    }

    public function onActivate(): void {}

    public function onDeactivate(): void {}
}
