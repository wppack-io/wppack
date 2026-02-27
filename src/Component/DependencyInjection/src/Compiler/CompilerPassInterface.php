<?php

declare(strict_types=1);

namespace WpPack\Component\DependencyInjection\Compiler;

use WpPack\Component\DependencyInjection\ContainerBuilder;

interface CompilerPassInterface
{
    public function process(ContainerBuilder $builder): void;
}
