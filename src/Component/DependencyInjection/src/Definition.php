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

namespace WpPack\Component\DependencyInjection;

use Symfony\Component\DependencyInjection\Definition as SymfonyDefinition;
use Symfony\Component\DependencyInjection\Reference as SymfonyReference;

class Definition
{
    private readonly SymfonyDefinition $symfonyDefinition;

    public function __construct(
        private readonly string $id,
        ?SymfonyDefinition $symfonyDefinition = null,
    ) {
        $this->symfonyDefinition = $symfonyDefinition ?? new SymfonyDefinition();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getSymfonyDefinition(): SymfonyDefinition
    {
        return $this->symfonyDefinition;
    }

    public function setArgument(int|string $key, mixed $value): self
    {
        $this->symfonyDefinition->setArgument($key, self::convertToSymfony($value));

        return $this;
    }

    public function addArgument(mixed $argument): self
    {
        $this->symfonyDefinition->addArgument(self::convertToSymfony($argument));

        return $this;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getArguments(): array
    {
        return self::convertFromSymfonyArray($this->symfonyDefinition->getArguments());
    }

    /**
     * @param array{0: Reference|string, 1: string} $factory
     */
    public function setFactory(array $factory): self
    {
        $symfonyFactory = $factory;
        if ($factory[0] instanceof Reference) {
            $symfonyFactory[0] = $factory[0]->toSymfony();
        }
        $this->symfonyDefinition->setFactory($symfonyFactory);

        return $this;
    }

    /**
     * @return array{0: Reference|string, 1: string}|null
     */
    public function getFactory(): ?array
    {
        $factory = $this->symfonyDefinition->getFactory();
        if ($factory === null) {
            return null;
        }
        if (!\is_array($factory)) {
            return null;
        }

        /** @var array{0: SymfonyReference|string, 1: string} $factory */
        $result = $factory;
        if ($factory[0] instanceof SymfonyReference) {
            $result[0] = Reference::fromSymfony($factory[0]);
        }

        /** @var array{0: Reference|string, 1: string} */
        return $result;
    }

    /**
     * @param list<mixed> $arguments
     */
    public function addMethodCall(string $method, array $arguments = []): self
    {
        $this->symfonyDefinition->addMethodCall($method, self::convertToSymfonyArray($arguments));

        return $this;
    }

    /**
     * @return list<array{method: string, arguments: list<mixed>}>
     */
    public function getMethodCalls(): array
    {
        $calls = $this->symfonyDefinition->getMethodCalls();
        $result = [];
        foreach ($calls as [$method, $arguments]) {
            $result[] = [
                'method' => $method,
                'arguments' => array_values(self::convertFromSymfonyArray($arguments)),
            ];
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function addTag(string $tag, array $attributes = []): self
    {
        $this->symfonyDefinition->addTag($tag, $attributes);

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getTags(): array
    {
        return array_keys($this->symfonyDefinition->getTags());
    }

    public function hasTag(string $tag): bool
    {
        return $this->symfonyDefinition->hasTag($tag);
    }

    public function autowire(): self
    {
        $this->symfonyDefinition->setAutowired(true);

        return $this;
    }

    public function setAutowired(bool $autowired): self
    {
        $this->symfonyDefinition->setAutowired($autowired);

        return $this;
    }

    public function isAutowired(): bool
    {
        return $this->symfonyDefinition->isAutowired();
    }

    public function setPublic(bool $public): self
    {
        $this->symfonyDefinition->setPublic($public);

        return $this;
    }

    public function isPublic(): bool
    {
        return $this->symfonyDefinition->isPublic();
    }

    public function setLazy(bool $lazy): self
    {
        $this->symfonyDefinition->setLazy($lazy);

        return $this;
    }

    public function isLazy(): bool
    {
        return $this->symfonyDefinition->isLazy();
    }

    public function setClass(?string $class): self
    {
        $this->symfonyDefinition->setClass($class);

        return $this;
    }

    public function getClass(): ?string
    {
        return $this->symfonyDefinition->getClass();
    }

    public function setAbstract(bool $abstract): self
    {
        $this->symfonyDefinition->setAbstract($abstract);

        return $this;
    }

    public function setDecoratedService(?string $id, ?string $renamedId = null, int $priority = 0): self
    {
        $this->symfonyDefinition->setDecoratedService($id, $renamedId, $priority);

        return $this;
    }

    /**
     * @internal
     */
    public static function wrap(string $id, SymfonyDefinition $symfonyDefinition): self
    {
        return new self($id, $symfonyDefinition);
    }

    /**
     * @return mixed
     */
    private static function convertToSymfony(mixed $value): mixed
    {
        if ($value instanceof Reference) {
            return $value->toSymfony();
        }
        if (\is_array($value)) {
            return self::convertToSymfonyArray($value);
        }

        return $value;
    }

    /**
     * @param array<int|string, mixed> $values
     * @return array<int|string, mixed>
     */
    private static function convertToSymfonyArray(array $values): array
    {
        $result = [];
        foreach ($values as $key => $value) {
            $result[$key] = self::convertToSymfony($value);
        }

        return $result;
    }

    /**
     * @return mixed
     */
    private static function convertFromSymfony(mixed $value): mixed
    {
        if ($value instanceof SymfonyReference) {
            return Reference::fromSymfony($value);
        }
        if (\is_array($value)) {
            return self::convertFromSymfonyArray($value);
        }

        return $value;
    }

    /**
     * @param array<int|string, mixed> $values
     * @return array<int|string, mixed>
     */
    private static function convertFromSymfonyArray(array $values): array
    {
        $result = [];
        foreach ($values as $key => $value) {
            $result[$key] = self::convertFromSymfony($value);
        }

        return $result;
    }
}
