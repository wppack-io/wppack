<?php

declare(strict_types=1);

namespace WpPack\Component\DependencyInjection;

class Definition
{
    /** @var array<int|string, mixed> */
    private array $arguments = [];

    /** @var array{0: Reference|string, 1: string}|null */
    private ?array $factory = null;

    /** @var list<array{method: string, arguments: list<mixed>}> */
    private array $methodCalls = [];

    /** @var list<string> */
    private array $tags = [];

    public function __construct(
        private readonly string $id,
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function setArgument(int|string $key, mixed $value): self
    {
        $this->arguments[$key] = $value;

        return $this;
    }

    public function addArgument(mixed $argument): self
    {
        $this->arguments[] = $argument;

        return $this;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * @param array{0: Reference|string, 1: string} $factory
     */
    public function setFactory(array $factory): self
    {
        $this->factory = $factory;

        return $this;
    }

    /**
     * @return array{0: Reference|string, 1: string}|null
     */
    public function getFactory(): ?array
    {
        return $this->factory;
    }

    /**
     * @param list<mixed> $arguments
     */
    public function addMethodCall(string $method, array $arguments = []): self
    {
        $this->methodCalls[] = ['method' => $method, 'arguments' => $arguments];

        return $this;
    }

    /**
     * @return list<array{method: string, arguments: list<mixed>}>
     */
    public function getMethodCalls(): array
    {
        return $this->methodCalls;
    }

    public function addTag(string $tag): self
    {
        $this->tags[] = $tag;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    public function autowire(): self
    {
        // Placeholder for autowiring support
        return $this;
    }
}
