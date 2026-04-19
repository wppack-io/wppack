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

final class InputOption
{
    public const VALUE_NONE = 1;
    public const VALUE_REQUIRED = 2;
    public const VALUE_OPTIONAL = 4;

    public function __construct(
        public readonly string $name,
        public readonly int $mode = self::VALUE_OPTIONAL,
        public readonly string $description = '',
        public readonly string|int|float|bool|null $default = null,
    ) {
        if ($name === '') {
            throw new InvalidArgumentException('An option name cannot be empty.');
        }

        if ($this->isValueNone() && $default !== null) {
            throw new InvalidArgumentException('A flag option (VALUE_NONE) cannot have a default value.');
        }
    }

    public function isValueNone(): bool
    {
        return ($this->mode & self::VALUE_NONE) !== 0;
    }

    public function isValueRequired(): bool
    {
        return ($this->mode & self::VALUE_REQUIRED) !== 0;
    }

    public function isValueOptional(): bool
    {
        return ($this->mode & self::VALUE_OPTIONAL) !== 0;
    }
}
