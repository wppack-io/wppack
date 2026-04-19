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

namespace WPPack\Component\DependencyInjection\Configurator;

use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\DependencyInjection\ServiceDiscovery;

final class PrototypeConfigurator
{
    /** @var list<string> */
    private array $excludes = [];

    public function __construct(
        private readonly string $namespace,
        private readonly string $resource,
    ) {}

    public function exclude(string ...$patterns): self
    {
        $this->excludes = array_merge($this->excludes, array_values($patterns));

        return $this;
    }

    /**
     * @internal
     */
    public function process(ContainerBuilder $builder, DefaultsConfigurator $defaults): void
    {
        $discovery = new ServiceDiscovery(
            builder: $builder,
            autowire: $defaults->isAutowire(),
            public: $defaults->isPublic(),
        );

        $discovery->discover($this->resource, $this->namespace, $this->excludes);
    }
}
