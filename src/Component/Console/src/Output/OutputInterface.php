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

namespace WpPack\Component\Console\Output;

interface OutputInterface
{
    public function write(string $message): void;

    public function writeln(string $message): void;

    public function newLine(int $count = 1): void;
}
