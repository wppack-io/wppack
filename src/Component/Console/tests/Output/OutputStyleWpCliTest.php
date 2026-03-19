<?php

declare(strict_types=1);

namespace WpPack\Component\Console\Tests\Output;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Console\Output\OutputStyle;
use WpPack\Component\Console\Output\WpCliOutput;

#[CoversClass(OutputStyle::class)]
final class OutputStyleWpCliTest extends TestCase
{
    protected function setUp(): void
    {
        require_once __DIR__ . '/../Fixtures/WpCliStub.php';
        \WP_CLI::reset();
    }

    #[Test]
    public function successCallsWpCliSuccess(): void
    {
        $style = new OutputStyle(new WpCliOutput());

        $style->success('All done');

        self::assertSame(['All done'], \WP_CLI::$successes);
    }

    #[Test]
    public function errorCallsWpCliError(): void
    {
        $style = new OutputStyle(new WpCliOutput());

        $style->error('Something failed');

        self::assertSame(['Something failed'], \WP_CLI::$errors);
    }

    #[Test]
    public function warningCallsWpCliWarning(): void
    {
        $style = new OutputStyle(new WpCliOutput());

        $style->warning('Watch out');

        self::assertSame(['Watch out'], \WP_CLI::$warnings);
    }

    #[Test]
    public function infoCallsWpCliLog(): void
    {
        $style = new OutputStyle(new WpCliOutput());

        $style->info('Processing...');

        self::assertSame(['Processing...'], \WP_CLI::$logged);
    }

    #[Test]
    public function lineCallsWriteln(): void
    {
        $style = new OutputStyle(new WpCliOutput());

        $style->line('plain text');

        self::assertSame(['plain text'], \WP_CLI::$logged);
    }

    #[Test]
    public function newLineCallsOutputNewLine(): void
    {
        $style = new OutputStyle(new WpCliOutput());

        $style->newLine(2);

        self::assertCount(2, \WP_CLI::$logged);
    }

    #[Test]
    public function getOutputReturnsWpCliOutput(): void
    {
        $wpCliOutput = new WpCliOutput();
        $style = new OutputStyle($wpCliOutput);

        self::assertSame($wpCliOutput, $style->getOutput());
    }

    #[Test]
    public function tableCallsFormatItems(): void
    {
        $style = new OutputStyle(new WpCliOutput());

        // format_items is available (either real or stub) -- should not throw
        $style->table(['Name', 'Age'], [['Alice', '30'], ['Bob', '25']]);

        // No exception means success
        self::assertTrue(true);
    }

    #[Test]
    public function progressCreatesProgressBar(): void
    {
        $style = new OutputStyle(new WpCliOutput());

        $progress = $style->progress(5, 'Importing');

        self::assertSame(0, $progress->getCurrent());
        self::assertSame(5, $progress->getTotal());
    }
}
