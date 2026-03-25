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

final class WpCliOutput implements OutputInterface
{
    public function write(string $message): void
    {
        \WP_CLI::out($message);
    }

    public function writeln(string $message): void
    {
        \WP_CLI::log($message);
    }

    public function newLine(int $count = 1): void
    {
        for ($i = 0; $i < $count; $i++) {
            \WP_CLI::log('');
        }
    }
}
