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

namespace WpPack\Component\Database\Schema;

final readonly class ColumnSchema
{
    public function __construct(
        public string $name,
        public string $type,
        public bool $nullable = false,
        public ?string $default = null,
        public string $extra = '',
        public bool $isBinary = false,
        public bool $isNumeric = false,
    ) {}
}
