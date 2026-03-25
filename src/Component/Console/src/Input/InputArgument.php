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

namespace WpPack\Component\Console\Input;

use WpPack\Component\Console\Exception\InvalidArgumentException;

final class InputArgument
{
    public const REQUIRED = 1;
    public const OPTIONAL = 2;
    public const IS_ARRAY = 4;

    public function __construct(
        public readonly string $name,
        public readonly int $mode = self::REQUIRED,
        public readonly string $description = '',
        public readonly string|int|float|bool|null $default = null,
    ) {
        if ($name === '') {
            throw new InvalidArgumentException('An argument name cannot be empty.');
        }

        if (($mode & self::REQUIRED) && $default !== null) {
            throw new InvalidArgumentException('A required argument cannot have a default value.');
        }
    }

    public function isRequired(): bool
    {
        return ($this->mode & self::REQUIRED) !== 0;
    }

    public function isArray(): bool
    {
        return ($this->mode & self::IS_ARRAY) !== 0;
    }
}
