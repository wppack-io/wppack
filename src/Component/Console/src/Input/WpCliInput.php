<?php

declare(strict_types=1);

namespace WpPack\Component\Console\Input;

use WpPack\Component\Console\Exception\InvalidArgumentException;

final class WpCliInput implements InputInterface
{
    /** @var array<string, string|int|float|bool|list<string>|null> */
    private readonly array $resolvedArguments;

    /** @var array<string, string|int|float|bool|null> */
    private readonly array $resolvedOptions;

    /**
     * @param list<string>          $args      WP-CLI positional arguments
     * @param array<string, string> $assocArgs WP-CLI associative arguments
     */
    public function __construct(
        InputDefinition $definition,
        array $args,
        array $assocArgs,
    ) {
        $this->resolvedArguments = $this->resolveArguments($definition, $args);
        $this->resolvedOptions = $this->resolveOptions($definition, $assocArgs);
    }

    public function getArgument(string $name): string|int|float|bool|array|null
    {
        if (!\array_key_exists($name, $this->resolvedArguments)) {
            throw new InvalidArgumentException(sprintf('The "%s" argument does not exist.', $name));
        }

        return $this->resolvedArguments[$name];
    }

    public function getOption(string $name): string|int|float|bool|null
    {
        if (!\array_key_exists($name, $this->resolvedOptions)) {
            throw new InvalidArgumentException(sprintf('The "--%s" option does not exist.', $name));
        }

        return $this->resolvedOptions[$name];
    }

    public function hasOption(string $name): bool
    {
        return \array_key_exists($name, $this->resolvedOptions);
    }

    /**
     * @param list<string> $args
     * @return array<string, string|int|float|bool|list<string>|null>
     */
    private function resolveArguments(InputDefinition $definition, array $args): array
    {
        $resolved = [];
        $index = 0;

        foreach ($definition->getArguments() as $name => $argument) {
            if ($argument->isArray()) {
                $resolved[$name] = \array_slice($args, $index);
                break;
            }

            if (isset($args[$index])) {
                $resolved[$name] = $args[$index];
                $index++;
            } elseif ($argument->isRequired()) {
                throw new InvalidArgumentException(sprintf('Not enough arguments (missing: "%s").', $name));
            } else {
                $resolved[$name] = $argument->default;
            }
        }

        return $resolved;
    }

    /**
     * @param array<string, string> $assocArgs
     * @return array<string, string|int|float|bool|null>
     */
    private function resolveOptions(InputDefinition $definition, array $assocArgs): array
    {
        $resolved = [];

        foreach ($definition->getOptions() as $name => $option) {
            if ($option->isValueNone()) {
                $resolved[$name] = \array_key_exists($name, $assocArgs);
            } elseif (\array_key_exists($name, $assocArgs)) {
                $resolved[$name] = $assocArgs[$name];
            } else {
                $resolved[$name] = $option->default;
            }
        }

        return $resolved;
    }
}
