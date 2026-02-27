<?php

declare(strict_types=1);

namespace WpPack\Component\SiteHealth\DependencyInjection;

use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\ServiceProviderInterface;
use WpPack\Component\SiteHealth\SiteHealthRegistry;

final class SiteHealthServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder->register(SiteHealthRegistry::class);
    }
}
