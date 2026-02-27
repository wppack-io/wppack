<?php

declare(strict_types=1);

namespace WpPack\Component\DependencyInjection;

use WpPack\Component\DependencyInjection\Compiler\CompilerPassInterface;

class ContainerBuilder
{
    /** @var array<string, Definition> */
    private array $definitions = [];

    /** @var array<string, array<string, list<array<string, mixed>>>> */
    private array $tags = [];

    /** @var CompilerPassInterface[] */
    private array $compilerPasses = [];

    public function register(string $id): Definition
    {
        $definition = new Definition($id);
        $this->definitions[$id] = $definition;

        return $definition;
    }

    public function findDefinition(string $id): Definition
    {
        if (!isset($this->definitions[$id])) {
            throw new \InvalidArgumentException(sprintf('Service "%s" is not defined.', $id));
        }

        return $this->definitions[$id];
    }

    public function hasDefinition(string $id): bool
    {
        return isset($this->definitions[$id]);
    }

    /**
     * @return array<string, Definition>
     */
    public function getDefinitions(): array
    {
        return $this->definitions;
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public function findTaggedServiceIds(string $tag): array
    {
        return $this->tags[$tag] ?? [];
    }

    /**
     * @param list<array<string, mixed>> $attributes
     */
    public function addTag(string $serviceId, string $tag, array $attributes = []): void
    {
        $this->tags[$tag][$serviceId][] = $attributes;
    }

    public function addCompilerPass(CompilerPassInterface $pass): self
    {
        $this->compilerPasses[] = $pass;

        return $this;
    }

    /**
     * @return CompilerPassInterface[]
     */
    public function getCompilerPasses(): array
    {
        return $this->compilerPasses;
    }
}
