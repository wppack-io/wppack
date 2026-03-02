<?php

declare(strict_types=1);

namespace WpPack\Component\DependencyInjection\Configurator;

use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\ServiceDiscovery;

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
