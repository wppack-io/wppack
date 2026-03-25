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

class AnotherPlugin extends AbstractPlugin
{
    public bool $registered = false;
    public bool $booted = false;

    public function __construct(string $pluginFile = __FILE__)
    {
        parent::__construct($pluginFile);
    }

    public function register(ContainerBuilder $builder): void
    {
        $this->registered = true;

        $builder->register(AnotherService::class, AnotherService::class)
            ->setPublic(true);
    }

    public function boot(Container $container): void
    {
        $this->booted = true;
    }
}
