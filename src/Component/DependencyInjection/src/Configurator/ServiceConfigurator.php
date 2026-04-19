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
use WPPack\Component\DependencyInjection\Reference;

final class ServiceConfigurator
{
    private ?bool $lazy = null;
    private ?bool $autowire = null;
    private ?bool $public = null;
    /** @var list<array{tag: string, attributes: array<string, mixed>}> */
    private array $tags = [];
    /** @var array<string|int, mixed> */
    private array $args = [];
    /** @var array{0: Reference|string, 1: string}|null */
    private ?array $factory = null;

    public function __construct(
        private readonly string $id,
        private readonly ?string $class = null,
    ) {}

    public function lazy(bool $lazy = true): self
    {
        $this->lazy = $lazy;

        return $this;
    }

    public function autowire(bool $autowire = true): self
    {
        $this->autowire = $autowire;

        return $this;
    }

    public function public(bool $public = true): self
    {
        $this->public = $public;

        return $this;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function tag(string $tag, array $attributes = []): self
    {
        $this->tags[] = ['tag' => $tag, 'attributes' => $attributes];

        return $this;
    }

    public function arg(string|int $key, mixed $value): self
    {
        $this->args[$key] = $value;

        return $this;
    }

    /**
     * @param array{0: Reference|string, 1: string} $factory
     */
    public function factory(array $factory): self
    {
        $this->factory = $factory;

        return $this;
    }

    /**
     * @internal
     */
    public function process(ContainerBuilder $builder, DefaultsConfigurator $defaults): void
    {
        $definition = $builder->hasDefinition($this->id)
            ? $builder->findDefinition($this->id)
            : $builder->register($this->id, $this->class ?? $this->id);

        if ($this->class !== null) {
            $definition->setClass($this->class);
        }

        $definition->setPublic($this->public ?? $defaults->isPublic());
        $definition->setAutowired($this->autowire ?? $defaults->isAutowire());

        if ($this->lazy !== null) {
            $definition->setLazy($this->lazy);
        }

        foreach ($this->tags as $tagEntry) {
            $definition->addTag($tagEntry['tag'], $tagEntry['attributes']);
        }

        foreach ($this->args as $key => $value) {
            $definition->setArgument($key, $value);
        }

        if ($this->factory !== null) {
            $definition->setFactory($this->factory);
        }
    }
}
