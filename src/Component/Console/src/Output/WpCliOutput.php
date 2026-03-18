<?php

declare(strict_types=1);

namespace WpPack\Component\Console\Output;

final class WpCliOutput implements OutputInterface
{
    public function write(string $message): void
    {
        \WP_CLI::out($message);    }

    public function writeln(string $message): void
    {
        \WP_CLI::log($message);    }

    public function newLine(int $count = 1): void
    {
        for ($i = 0; $i < $count; $i++) {
            \WP_CLI::log('');        }
    }
}
