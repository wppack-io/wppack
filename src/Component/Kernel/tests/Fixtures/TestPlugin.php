<?php
/*
 * This file is part of the WpPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WpPack\Component\Kernel\Tests\Fixtures;

use WpPack\Component\DependencyInjection\Container;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\Kernel\AbstractPlugin;

class TestPlugin extends AbstractPlugin
{
    public bool $registered = false;
    public bool $booted = false;
    public ?Container $bootedContainer = null;

    /** @var list<\WpPack\Component\DependencyInjection\Compiler\CompilerPassInterface> */
    private array $compilerPasses;

    /**
     * @param list<\WpPack\Component\DependencyInjection\Compiler\CompilerPassInterface> $compilerPasses
     */
    public function __construct(string $pluginFile = __FILE__, array $compilerPasses = [])
    {
        parent::__construct($pluginFile);
        $this->compilerPasses = $compilerPasses;
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
}
