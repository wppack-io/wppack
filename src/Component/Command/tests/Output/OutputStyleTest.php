<?php

declare(strict_types=1);

namespace WpPack\Component\Command\Tests\Output;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Command\Output\BufferedOutput;
use WpPack\Component\Command\Output\OutputStyle;

final class OutputStyleTest extends TestCase
{
    private BufferedOutput $buffer;
    private OutputStyle $style;

    protected function setUp(): void
    {
        $this->buffer = new BufferedOutput();
        $this->style = new OutputStyle($this->buffer);
    }

    #[Test]
    public function success(): void
    {
        $this->style->success('Done!');

        self::assertSame('[SUCCESS] Done!' . \PHP_EOL, $this->buffer->getBuffer());
    }

    #[Test]
    public function error(): void
    {
        $this->style->error('Failed!');

        self::assertSame('[ERROR] Failed!' . \PHP_EOL, $this->buffer->getBuffer());
    }

    #[Test]
    public function warning(): void
    {
        $this->style->warning('Watch out!');

        self::assertSame('[WARNING] Watch out!' . \PHP_EOL, $this->buffer->getBuffer());
    }

    #[Test]
    public function info(): void
    {
        $this->style->info('Processing...');

        self::assertSame('[INFO] Processing...' . \PHP_EOL, $this->buffer->getBuffer());
    }

    #[Test]
    public function line(): void
    {
        $this->style->line('Plain text');

        self::assertSame('Plain text' . \PHP_EOL, $this->buffer->getBuffer());
    }

    #[Test]
    public function newLine(): void
    {
        $this->style->newLine(2);

        self::assertSame(\PHP_EOL . \PHP_EOL, $this->buffer->getBuffer());
    }

    #[Test]
    public function table(): void
    {
        $this->style->table(['Name', 'Age'], [['Alice', '30'], ['Bob', '25']]);

        $output = $this->buffer->getBuffer();

        self::assertStringContainsString('Name', $output);
        self::assertStringContainsString('Age', $output);
        self::assertStringContainsString('Alice', $output);
        self::assertStringContainsString('30', $output);
        self::assertStringContainsString('Bob', $output);
        self::assertStringContainsString('25', $output);
    }

    #[Test]
    public function progress(): void
    {
        $progress = $this->style->progress(10, 'Importing');

        self::assertSame(0, $progress->getCurrent());
        self::assertSame(10, $progress->getTotal());

        $progress->advance();
        self::assertSame(1, $progress->getCurrent());

        $progress->finish();
        self::assertSame(10, $progress->getCurrent());

        $output = $this->buffer->getBuffer();
        self::assertStringContainsString('Importing', $output);
        self::assertStringContainsString('0/10', $output);
        self::assertStringContainsString('1/10', $output);
        self::assertStringContainsString('10/10', $output);
    }

    #[Test]
    public function confirmReturnsFallbackDefault(): void
    {
        self::assertTrue($this->style->confirm('Continue?', true));
        self::assertFalse($this->style->confirm('Continue?', false));
    }

    #[Test]
    public function askReturnsFallbackDefault(): void
    {
        self::assertSame('default', $this->style->ask('Name?', 'default'));
        self::assertSame('', $this->style->ask('Name?'));
    }

    #[Test]
    public function getOutput(): void
    {
        self::assertSame($this->buffer, $this->style->getOutput());
    }
}
