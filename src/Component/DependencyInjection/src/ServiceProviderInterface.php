<?php

declare(strict_types=1);

namespace WpPack\Component\DependencyInjection;

interface ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void;
}
