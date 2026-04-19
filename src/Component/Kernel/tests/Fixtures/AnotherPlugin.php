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
use WPPack\Component\Kernel\AbstractPlugin;

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
