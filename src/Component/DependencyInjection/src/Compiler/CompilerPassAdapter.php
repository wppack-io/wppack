<?php

declare(strict_types=1);

namespace WpPack\Component\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface as SymfonyCompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder as SymfonyContainerBuilder;
use WpPack\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
final class CompilerPassAdapter implements SymfonyCompilerPassInterface
{
    public function __construct(
        private readonly CompilerPassInterface $pass,
        private readonly ContainerBuilder $wpPackBuilder,
    ) {}

    public function process(SymfonyContainerBuilder $container): void
    {
        $this->pass->process($this->wpPackBuilder);
    }
}
