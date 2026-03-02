<?php

declare(strict_types=1);

namespace WpPack\Component\Config;

use WpPack\Component\Config\Attribute\Constant;
use WpPack\Component\Config\Attribute\Env;
use WpPack\Component\Config\Attribute\Option;
use WpPack\Component\Config\Exception\ConfigResolverException;

final class ConfigResolver
{
    /**
     * @template T of object
     * @param class-string<T> $className
     * @return T
     */
    public function resolve(string $className): object
    {
        $reflection = new \ReflectionClass($className);
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return $reflection->newInstance();
        }

        $arguments = [];

        foreach ($constructor->getParameters() as $parameter) {
            $arguments[] = $this->resolveParameter($className, $parameter);
        }

        return $reflection->newInstanceArgs($arguments);
    }

    private function resolveParameter(string $className, \ReflectionParameter $parameter): mixed
    {
        $envAttributes = $parameter->getAttributes(Env::class);
        if ($envAttributes !== []) {
            return $this->resolveEnv($className, $parameter, $envAttributes[0]->newInstance());
        }

        $optionAttributes = $parameter->getAttributes(Option::class);
        if ($optionAttributes !== []) {
            return $this->resolveOption($className, $parameter, $optionAttributes[0]->newInstance());
        }

        $constantAttributes = $parameter->getAttributes(Constant::class);
        if ($constantAttributes !== []) {
            return $this->resolveConstant($className, $parameter, $constantAttributes[0]->newInstance());
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        throw ConfigResolverException::missingValue(
            $className,
            $parameter->getName(),
            'No config attribute',
        );
    }

    private function resolveEnv(string $className, \ReflectionParameter $parameter, Env $env): mixed
    {
        $value = $_ENV[$env->name] ?? getenv($env->name);

        if ($value === false) {
            if ($parameter->isDefaultValueAvailable()) {
                return $parameter->getDefaultValue();
            }

            throw ConfigResolverException::missingValue(
                $className,
                $parameter->getName(),
                sprintf('Environment variable "%s"', $env->name),
            );
        }

        return $this->castValue($value, $parameter);
    }

    private function resolveOption(string $className, \ReflectionParameter $parameter, Option $option): mixed
    {
        if (!function_exists('get_option')) {
            if ($parameter->isDefaultValueAvailable()) {
                return $parameter->getDefaultValue();
            }

            throw ConfigResolverException::missingValue(
                $className,
                $parameter->getName(),
                sprintf('WordPress option "%s" (get_option not available)', $option->name),
            );
        }

        $parts = explode('.', $option->name);
        $optionName = array_shift($parts);

        $sentinel = new \stdClass();
        $value = get_option($optionName, $sentinel);

        if ($value === $sentinel) {
            if ($parameter->isDefaultValueAvailable()) {
                return $parameter->getDefaultValue();
            }

            throw ConfigResolverException::missingValue(
                $className,
                $parameter->getName(),
                sprintf('WordPress option "%s"', $option->name),
            );
        }

        foreach ($parts as $key) {
            if (!\is_array($value) || !\array_key_exists($key, $value)) {
                if ($parameter->isDefaultValueAvailable()) {
                    return $parameter->getDefaultValue();
                }

                throw ConfigResolverException::missingValue(
                    $className,
                    $parameter->getName(),
                    sprintf('WordPress option "%s"', $option->name),
                );
            }

            $value = $value[$key];
        }

        return $this->castValue($value, $parameter);
    }

    private function resolveConstant(string $className, \ReflectionParameter $parameter, Constant $constant): mixed
    {
        if (!defined($constant->name)) {
            if ($parameter->isDefaultValueAvailable()) {
                return $parameter->getDefaultValue();
            }

            throw ConfigResolverException::missingValue(
                $className,
                $parameter->getName(),
                sprintf('Constant "%s"', $constant->name),
            );
        }

        return $this->castValue(constant($constant->name), $parameter);
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
}
