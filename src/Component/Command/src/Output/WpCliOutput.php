<?php

declare(strict_types=1);

namespace WpPack\Component\Command\Output;

final class WpCliOutput implements OutputInterface
{
    public function write(string $message): void
    {
        \WP_CLI::log($message); // @phpstan-ignore class.notFound
    }

    public function writeln(string $message): void
    {
        \WP_CLI::log($message); // @phpstan-ignore class.notFound
    }

    public function newLine(int $count = 1): void
    {
        for ($i = 0; $i < $count; $i++) {
            \WP_CLI::log(''); // @phpstan-ignore class.notFound
        }
    }
}
