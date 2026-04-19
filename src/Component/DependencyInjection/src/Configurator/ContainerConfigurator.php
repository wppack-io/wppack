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

final class ContainerConfigurator
{
    private readonly DefaultsConfigurator $defaults;

    /** @var list<PrototypeConfigurator> */
    private array $prototypes = [];

    /** @var list<ServiceConfigurator> */
    private array $services = [];

    /** @var list<array{alias: string, id: string}> */
    private array $aliases = [];

    /** @var array<string, mixed> */
    private array $parameters = [];

    public function __construct(
        private readonly ContainerBuilder $builder,
    ) {
        $this->defaults = new DefaultsConfigurator();
    }

    public function defaults(): DefaultsConfigurator
    {
        return $this->defaults;
    }

    public function load(string $namespace, string $resource): PrototypeConfigurator
    {
        $prototype = new PrototypeConfigurator($namespace, $resource);
        $this->prototypes[] = $prototype;

        return $prototype;
    }

    public function set(string $id, ?string $class = null): ServiceConfigurator
    {
        $service = new ServiceConfigurator($id, $class);
        $this->services[] = $service;

        return $service;
    }

    public function alias(string $alias, string $id): self
    {
        $this->aliases[] = ['alias' => $alias, 'id' => $id];

        return $this;
    }

    public function param(string $name, mixed $value): self
    {
        $this->parameters[$name] = $value;

        return $this;
    }

    /**
     * @internal
     */
    public function process(): void
    {
        foreach ($this->parameters as $name => $value) {
            $this->builder->setParameter($name, $value);
        }

        foreach ($this->prototypes as $prototype) {
            $prototype->process($this->builder, $this->defaults);
        }

        foreach ($this->services as $service) {
            $service->process($this->builder, $this->defaults);
        }

        foreach ($this->aliases as $aliasEntry) {
            $this->builder->setAlias($aliasEntry['alias'], $aliasEntry['id']);
        }
    }
}
