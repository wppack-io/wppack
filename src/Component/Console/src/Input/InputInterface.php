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

interface InputInterface
{
    /** @return string|int|float|bool|list<string>|null */
    public function getArgument(string $name): string|int|float|bool|array|null;

    public function getOption(string $name): string|int|float|bool|null;

    public function hasOption(string $name): bool;
}
