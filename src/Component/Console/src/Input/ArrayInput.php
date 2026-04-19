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

namespace WPPack\Component\Console\Input;

use WPPack\Component\Console\Exception\InvalidArgumentException;

final class ArrayInput implements InputInterface
{
    /**
     * @param array<string, string|int|float|bool|list<string>|null> $arguments
     * @param array<string, string|int|float|bool|null>              $options
     */
    public function __construct(
        private readonly array $arguments = [],
        private readonly array $options = [],
    ) {}

    public function getArgument(string $name): string|int|float|bool|array|null
    {
        if (!\array_key_exists($name, $this->arguments)) {
            throw new InvalidArgumentException(sprintf('The "%s" argument does not exist.', $name));
        }

        return $this->arguments[$name];
    }

    public function getOption(string $name): string|int|float|bool|null
    {
        if (!\array_key_exists($name, $this->options)) {
            throw new InvalidArgumentException(sprintf('The "--%s" option does not exist.', $name));
        }

        return $this->options[$name];
    }

    public function hasOption(string $name): bool
    {
        return \array_key_exists($name, $this->options);
    }
}
