<?php

declare(strict_types=1);

namespace WpPack\Component\Command\Input;

use WpPack\Component\Command\Exception\InvalidArgumentException;
use WpPack\Component\Command\Exception\LogicException;

final class InputDefinition
{
    /** @var array<string, InputArgument> */
    private array $arguments = [];

    /** @var array<string, InputOption> */
    private array $options = [];

    private bool $hasAnArrayArgument = false;
    private bool $hasOptional = false;

    public function addArgument(InputArgument $argument): self
    {
        if (isset($this->arguments[$argument->name])) {
            throw new LogicException(sprintf('An argument with name "%s" already exists.', $argument->name));
        }

        if ($this->hasAnArrayArgument) {
            throw new LogicException('Cannot add an argument after an array argument.');
        }

        if ($argument->isRequired() && $this->hasOptional) {
            throw new LogicException('Cannot add a required argument after an optional one.');
        }

        if ($argument->isArray()) {
            $this->hasAnArrayArgument = true;
        }

        if (!$argument->isRequired()) {
            $this->hasOptional = true;
        }

        $this->arguments[$argument->name] = $argument;

        return $this;
    }

    public function addOption(InputOption $option): self
    {
        if (isset($this->options[$option->name])) {
            throw new LogicException(sprintf('An option with name "%s" already exists.', $option->name));
        }

        $this->options[$option->name] = $option;

        return $this;
    }

    public function getArgument(string $name): InputArgument
    {
        if (!isset($this->arguments[$name])) {
            throw new InvalidArgumentException(sprintf('The "%s" argument does not exist.', $name));
        }

        return $this->arguments[$name];
    }

    public function getOption(string $name): InputOption
    {
        if (!isset($this->options[$name])) {
            throw new InvalidArgumentException(sprintf('The "--%s" option does not exist.', $name));
        }

        return $this->options[$name];
    }

    /** @return array<string, InputArgument> */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /** @return array<string, InputOption> */
    public function getOptions(): array
    {
        return $this->options;
    }

    public function hasArgument(string $name): bool
    {
        return isset($this->arguments[$name]);
    }

    public function hasOption(string $name): bool
    {
        return isset($this->options[$name]);
    }

    /**
     * Generate WP-CLI synopsis array.
     *
     * @return list<array<string, mixed>>
     */
    public function toSynopsis(): array
    {
        $synopsis = [];

        foreach ($this->arguments as $argument) {
            $entry = [
                'type' => 'positional',
                'name' => $argument->name,
                'description' => $argument->description,
                'optional' => !$argument->isRequired(),
            ];

            if ($argument->isArray()) {
                $entry['repeating'] = true;
            }

            if ($argument->default !== null) {
                $entry['default'] = $argument->default;
            }

            $synopsis[] = $entry;
        }

        foreach ($this->options as $option) {
            if ($option->isValueNone()) {
                $entry = [
                    'type' => 'flag',
                    'name' => $option->name,
                    'description' => $option->description,
                    'optional' => true,
                ];
            } else {
                $entry = [
                    'type' => 'assoc',
                    'name' => $option->name,
                    'description' => $option->description,
                    'optional' => !$option->isValueRequired(),
                ];

                if ($option->default !== null) {
                    $entry['default'] = $option->default;
                }
            }

            $synopsis[] = $entry;
        }

        return $synopsis;
    }
}
