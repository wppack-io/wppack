<?php

declare(strict_types=1);

namespace WpPack\Component\Command\Output;

final class BufferedOutput implements OutputInterface
{
    private string $buffer = '';

    public function write(string $message): void
    {
        $this->buffer .= $message;
    }

    public function writeln(string $message): void
    {
        $this->buffer .= $message . \PHP_EOL;
    }

    public function newLine(int $count = 1): void
    {
        $this->buffer .= str_repeat(\PHP_EOL, $count);
    }

    public function fetch(): string
    {
        $content = $this->buffer;
        $this->buffer = '';

        return $content;
    }

    public function getBuffer(): string
    {
        return $this->buffer;
    }
}
