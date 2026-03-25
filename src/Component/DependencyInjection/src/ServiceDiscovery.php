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

use WpPack\Component\DependencyInjection\Attribute\AsAlias;
use WpPack\Component\DependencyInjection\Attribute\Autowire;
use WpPack\Component\DependencyInjection\Attribute\Exclude;

final class ServiceDiscovery
{
    public function __construct(
        private readonly ContainerBuilder $builder,
        private readonly bool $autowire = true,
        private readonly bool $public = true,
    ) {}

    /**
     * @param list<string> $excludes
     */
    public function discover(string $directory, string $namespace, array $excludes = []): void
    {
        $directory = rtrim($directory, '/\\');
        $namespace = rtrim($namespace, '\\') . '\\';

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relativePath = substr($file->getPathname(), \strlen($directory) + 1);

            if ($this->isExcludedByPattern($relativePath, $excludes)) {
                continue;
            }

            $className = $namespace . str_replace(
                ['/', '.php'],
                ['\\', ''],
                $relativePath,
            );

            if (!class_exists($className)) {
                continue;
            }

            $this->registerClass($className);
        }
    }

    private function registerClass(string $className): void
    {
        $reflection = new \ReflectionClass($className);

        if ($reflection->isAbstract() || $reflection->isInterface()) {
            return;
        }

        if ($reflection->getAttributes(Exclude::class) !== []) {
            return;
        }

        $definition = $this->builder->register($className, $className);
        $definition->setPublic($this->public);

        if ($this->autowire) {
            $definition->autowire();
        }

        $this->processAutowireAttributes($reflection, $definition);
        $this->processAliasAttributes($reflection, $className);
    }

    /**
     * @param \ReflectionClass<object> $reflection
     */
    private function processAutowireAttributes(\ReflectionClass $reflection, Definition $definition): void
    {
        $constructor = $reflection->getConstructor();
        if ($constructor === null) {
            return;
        }

        foreach ($constructor->getParameters() as $parameter) {
            $attributes = $parameter->getAttributes(Autowire::class, \ReflectionAttribute::IS_INSTANCEOF);
            if ($attributes === []) {
                continue;
            }

            /** @var Autowire $autowire */
            $autowire = $attributes[0]->newInstance();

            $this->resolveAutowireAttribute($autowire, $parameter, $definition);
        }
    }

    private function resolveAutowireAttribute(Autowire $autowire, \ReflectionParameter $parameter, Definition $definition): void
    {
        $argName = '$' . $parameter->getName();

        if ($autowire->env !== null) {
            $value = $_ENV[$autowire->env] ?? getenv($autowire->env);

            if ($value === false) {
                if ($parameter->isDefaultValueAvailable()) {
                    return;
                }

                $definition->setArgument($argName, sprintf('%%env(%s)%%', $autowire->env));

                return;
            }

            $definition->setArgument($argName, $this->castValue($value, $parameter));

            return;
        }

        if ($autowire->param !== null) {
            $definition->setArgument($argName, sprintf('%%%s%%', $autowire->param));

            return;
        }

        if ($autowire->service !== null) {
            $definition->setArgument($argName, new Reference($autowire->service));

            return;
        }

        if ($autowire->option !== null) {
            $resolved = $this->resolveOption($autowire->option, $parameter);
            if ($resolved !== null) {
                $definition->setArgument($argName, $resolved);
            }

            return;
        }

        if ($autowire->constant !== null) {
            $resolved = $this->resolveConstant($autowire->constant, $parameter);
            if ($resolved !== null) {
                $definition->setArgument($argName, $resolved);
            }
        }
    }

    private function resolveOption(string $name, \ReflectionParameter $parameter): mixed
    {
        $parts = explode('.', $name);
        $optionName = array_shift($parts);

        $sentinel = new \stdClass();
        $value = get_option($optionName, $sentinel);

        if ($value === $sentinel) {
            if ($parameter->isDefaultValueAvailable()) {
                return null;
            }

            throw new \RuntimeException(sprintf(
                'Unable to resolve option "%s" for parameter "$%s": option is not set and no default value is provided.',
                $name,
                $parameter->getName(),
            ));
        }

        foreach ($parts as $key) {
            if (!\is_array($value) || !\array_key_exists($key, $value)) {
                if ($parameter->isDefaultValueAvailable()) {
                    return null;
                }

                throw new \RuntimeException(sprintf(
                    'Unable to resolve option "%s" for parameter "$%s": nested key "%s" not found and no default value is provided.',
                    $name,
                    $parameter->getName(),
                    $key,
                ));
            }

            $value = $value[$key];
        }

        return $this->castValue($value, $parameter);
    }

    private function resolveConstant(string $name, \ReflectionParameter $parameter): mixed
    {
        if (!defined($name)) {
            if ($parameter->isDefaultValueAvailable()) {
                return null;
            }

            throw new \RuntimeException(sprintf(
                'Unable to resolve constant "%s" for parameter "$%s": constant is not defined and no default value is provided.',
                $name,
                $parameter->getName(),
            ));
        }

        return $this->castValue(constant($name), $parameter);
    }

    private function castValue(mixed $value, \ReflectionParameter $parameter): mixed
    {
        $type = $parameter->getType();

        if (!$type instanceof \ReflectionNamedType) {
            return $value;
        }

        return match ($type->getName()) {
            'string' => (string) $value,
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => $this->castBool($value),
            'array' => (array) $value,
            default => $value,
        };
    }

    private function castBool(mixed $value): bool
    {
        if (\is_string($value)) {
            return !\in_array(strtolower($value), ['', '0', 'false', 'no', 'off'], true);
        }

        return (bool) $value;
    }

    /**
     * @param list<string> $excludes
     */
    private function isExcludedByPattern(string $relativePath, array $excludes): bool
    {
        foreach ($excludes as $pattern) {
            if (fnmatch($pattern, $relativePath)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param \ReflectionClass<object> $reflection
     */
    private function processAliasAttributes(\ReflectionClass $reflection, string $className): void
    {
        $attributes = $reflection->getAttributes(AsAlias::class);
        foreach ($attributes as $attribute) {
            /** @var AsAlias $asAlias */
            $asAlias = $attribute->newInstance();
            $this->builder->setAlias($asAlias->id, $className);
        }
    }
}
