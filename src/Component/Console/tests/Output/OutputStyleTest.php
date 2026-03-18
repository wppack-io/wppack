<?php

declare(strict_types=1);

namespace WpPack\Component\Console\Tests\Output;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Console\Output\BufferedOutput;
use WpPack\Component\Console\Output\OutputStyle;

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
    public function newLineDefault(): void
    {
        $this->style->newLine();

        self::assertSame(\PHP_EOL, $this->buffer->getBuffer());
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
    public function tableOutputsTabSeparatedFormat(): void
    {
        $this->style->table(['Col1', 'Col2'], [['a', 'b'], ['c', 'd']]);

        $output = $this->buffer->getBuffer();
        $lines = explode(\PHP_EOL, trim($output));

        // Header line
        self::assertSame("Col1\tCol2", $lines[0]);
        // Data rows
        self::assertSame("a\tb", $lines[1]);
        self::assertSame("c\td", $lines[2]);
    }

    #[Test]
    public function tableWithEmptyRows(): void
    {
        $this->style->table(['Header'], []);

        $output = $this->buffer->getBuffer();

        self::assertStringContainsString('Header', $output);
    }

    #[Test]
    public function tableWithNumericValues(): void
    {
        $this->style->table(['Name', 'Score'], [['Alice', 95], ['Bob', 87]]);

        $output = $this->buffer->getBuffer();

        self::assertStringContainsString('95', $output);
        self::assertStringContainsString('87', $output);
    }

    #[Test]
    public function tableWithMissingColumns(): void
    {
        // Row shorter than headers — should handle gracefully
        $this->style->table(['A', 'B', 'C'], [['x']]);

        $output = $this->buffer->getBuffer();
        self::assertStringContainsString('x', $output);
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
    public function confirmOutputsQuestionWithYes(): void
    {
        $this->style->confirm('Continue?', true);

        $output = $this->buffer->getBuffer();
        self::assertStringContainsString('Continue?', $output);
        self::assertStringContainsString('[Y/n]', $output);
    }

    #[Test]
    public function confirmOutputsQuestionWithNo(): void
    {
        $this->style->confirm('Continue?', false);

        $output = $this->buffer->getBuffer();
        self::assertStringContainsString('Continue?', $output);
        self::assertStringContainsString('[y/N]', $output);
    }

    #[Test]
    public function askReturnsFallbackDefault(): void
    {
        self::assertSame('default', $this->style->ask('Name?', 'default'));
        self::assertSame('', $this->style->ask('Name?'));
    }

    #[Test]
    public function askOutputsPromptWithDefault(): void
    {
        $this->style->ask('Enter name?', 'John');

        $output = $this->buffer->getBuffer();
        self::assertStringContainsString('Enter name?', $output);
        self::assertStringContainsString('[John]', $output);
    }

    #[Test]
    public function askOutputsPromptWithoutDefault(): void
    {
        $this->style->ask('Enter name?');

        $output = $this->buffer->getBuffer();
        self::assertStringContainsString('Enter name?', $output);
    }

    #[Test]
    public function getOutput(): void
    {
        self::assertSame($this->buffer, $this->style->getOutput());
    }

    #[Test]
    public function multipleOutputCallsAppend(): void
    {
        $this->style->success('Step 1');
        $this->style->info('Step 2');
        $this->style->error('Step 3');

        $output = $this->buffer->getBuffer();
        self::assertStringContainsString('[SUCCESS] Step 1', $output);
        self::assertStringContainsString('[INFO] Step 2', $output);
        self::assertStringContainsString('[ERROR] Step 3', $output);
    }
}
