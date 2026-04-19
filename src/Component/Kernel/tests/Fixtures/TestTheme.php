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

namespace WPPack\Component\Kernel\Tests\Fixtures;

use WPPack\Component\DependencyInjection\Container;
use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\Kernel\AbstractTheme;

class TestTheme extends AbstractTheme
{
    public bool $registered = false;
    public bool $booted = false;
    public ?Container $bootedContainer = null;

    /** @var list<\WPPack\Component\DependencyInjection\Compiler\CompilerPassInterface> */
    private array $compilerPasses;

    /**
     * @param list<\WPPack\Component\DependencyInjection\Compiler\CompilerPassInterface> $compilerPasses
     */
    public function __construct(string $themeFile = __FILE__, array $compilerPasses = [])
    {
        parent::__construct($themeFile);
        $this->compilerPasses = $compilerPasses;
    }

    public function register(ContainerBuilder $builder): void
    {
        $this->registered = true;
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
