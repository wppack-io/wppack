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

namespace WPPack\Component\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder as SymfonyContainerBuilder;
use WPPack\Component\DependencyInjection\Compiler\CompilerPassAdapter;
use WPPack\Component\DependencyInjection\Compiler\CompilerPassInterface;
use WPPack\Component\DependencyInjection\Configurator\ContainerConfigurator;
use WPPack\Component\DependencyInjection\Exception\ParameterNotFoundException;
use WPPack\Component\DependencyInjection\Exception\ServiceNotFoundException;

class ContainerBuilder
{
    private const INTERNAL_SERVICE_IDS = ['service_container'];

    private readonly SymfonyContainerBuilder $symfonyBuilder;

    /** @var array<string, Definition> */
    private array $definitions = [];

    /** @var CompilerPassInterface[] */
    private array $compilerPasses = [];

    public function __construct(?SymfonyContainerBuilder $symfonyBuilder = null)
    {
        $this->symfonyBuilder = $symfonyBuilder ?? new SymfonyContainerBuilder();
    }

    /**
     * @internal
     */
    public function getSymfonyBuilder(): SymfonyContainerBuilder
    {
        return $this->symfonyBuilder;
    }

    public function register(string $id, ?string $class = null): Definition
    {
        $symfonyDefinition = $this->symfonyBuilder->register($id, $class);
        $symfonyDefinition->setPublic(true);

        $definition = Definition::wrap($id, $symfonyDefinition);
        $this->definitions[$id] = $definition;

        return $definition;
    }

    public function findDefinition(string $id): Definition
    {
        if (isset($this->definitions[$id])) {
            return $this->definitions[$id];
        }

        if ($this->symfonyBuilder->hasDefinition($id)) {
            $symfonyDefinition = $this->symfonyBuilder->findDefinition($id);
            $definition = Definition::wrap($id, $symfonyDefinition);
            $this->definitions[$id] = $definition;

            return $definition;
        }

        throw new ServiceNotFoundException($id);
    }

    public function hasDefinition(string $id): bool
    {
        return isset($this->definitions[$id]) || $this->symfonyBuilder->hasDefinition($id);
    }

    /**
     * @return array<string, Definition>
     */
    public function all(): array
    {
        foreach ($this->symfonyBuilder->getDefinitions() as $id => $symfonyDefinition) {
            if (\in_array($id, self::INTERNAL_SERVICE_IDS, true)) {
                continue;
            }
            if (!isset($this->definitions[$id])) {
                $this->definitions[$id] = Definition::wrap($id, $symfonyDefinition);
            }
        }

        return $this->definitions;
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public function findTaggedServiceIds(string $tag): array
    {
        $result = [];
        foreach ($this->symfonyBuilder->findTaggedServiceIds($tag) as $serviceId => $tags) {
            $result[$serviceId] = array_values($tags);
        }

        return $result;
    }

    public function addCompilerPass(CompilerPassInterface $pass): self
    {
        $this->compilerPasses[] = $pass;
        $this->symfonyBuilder->addCompilerPass(new CompilerPassAdapter($pass, $this));

        return $this;
    }

    /**
     * @return CompilerPassInterface[]
     */
    public function getCompilerPasses(): array
    {
        return $this->compilerPasses;
    }

    public function compile(): Container
    {
        $this->symfonyBuilder->compile();

        return new Container($this->symfonyBuilder);
    }

    public function setParameter(string $name, mixed $value): self
    {
        $this->symfonyBuilder->setParameter($name, $value);

        return $this;
    }

    public function getParameter(string $name): mixed
    {
        if (!$this->symfonyBuilder->hasParameter($name)) {
            throw new ParameterNotFoundException($name);
        }

        return $this->symfonyBuilder->getParameter($name);
    }

    public function hasParameter(string $name): bool
    {
        return $this->symfonyBuilder->hasParameter($name);
    }

    public function setAlias(string $alias, string $id): self
    {
        $this->symfonyBuilder->setAlias($alias, $id)->setPublic(true);

        return $this;
    }

    public function addServiceProvider(ServiceProviderInterface $provider): self
    {
        $provider->register($this);

        return $this;
    }

    public function loadConfig(string $path): self
    {
        $configurator = new ContainerConfigurator($this);

        /** @var \Closure(ContainerConfigurator): void $callback */
        $callback = require $path;
        $callback($configurator);
        $configurator->process();

        return $this;
    }
}
