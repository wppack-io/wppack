<?php

declare(strict_types=1);

namespace WpPack\Component\Command\Output;

interface OutputInterface
{
    public function write(string $message): void;

    public function writeln(string $message): void;

    public function newLine(int $count = 1): void;
}
