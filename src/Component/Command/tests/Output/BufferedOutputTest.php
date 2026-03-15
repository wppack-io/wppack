<?php

declare(strict_types=1);

namespace WpPack\Component\Command\Tests\Output;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Command\Output\BufferedOutput;

final class BufferedOutputTest extends TestCase
{
    #[Test]
    public function write(): void
    {
        $output = new BufferedOutput();
        $output->write('hello');
        $output->write(' world');

        self::assertSame('hello world', $output->getBuffer());
    }

    #[Test]
    public function writeln(): void
    {
        $output = new BufferedOutput();
        $output->writeln('hello');

        self::assertSame('hello' . \PHP_EOL, $output->getBuffer());
    }

    #[Test]
    public function newLine(): void
    {
        $output = new BufferedOutput();
        $output->newLine(3);

        self::assertSame(\PHP_EOL . \PHP_EOL . \PHP_EOL, $output->getBuffer());
    }

    #[Test]
    public function fetch(): void
    {
        $output = new BufferedOutput();
        $output->writeln('hello');

        $content = $output->fetch();

        self::assertSame('hello' . \PHP_EOL, $content);
        self::assertSame('', $output->getBuffer());
    }
}
