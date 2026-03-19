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
    #[Test]
    public function successCallsWpCliSuccess(): void
    {
        $style = new OutputStyle(new WpCliOutput());

        // WP_CLI::success() requires logger; without one it's a no-op
        $style->success('All done');

        self::assertTrue(true);
    }

    #[Test]
    public function warningCallsWpCliWarning(): void
    {
        $style = new OutputStyle(new WpCliOutput());

        // WP_CLI::warning() requires logger; without one it's a no-op
        $style->warning('Watch out');

        self::assertTrue(true);
    }

    #[Test]
    public function infoCallsWpCliLog(): void
    {
        $style = new OutputStyle(new WpCliOutput());

        $style->info('Processing...');

        self::assertTrue(true);
    }

    #[Test]
    public function lineCallsWriteln(): void
    {
        $style = new OutputStyle(new WpCliOutput());

        $style->line('plain text');

        self::assertTrue(true);
    }

    #[Test]
    public function newLineCallsOutputNewLine(): void
    {
        $style = new OutputStyle(new WpCliOutput());

        $style->newLine(2);

        self::assertTrue(true);
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
        require_once dirname(__DIR__, 5) . '/vendor/wp-cli/wp-cli/php/utils.php';

        $style = new OutputStyle(new WpCliOutput());

        $style->table(['Name', 'Age'], [['Alice', '30'], ['Bob', '25']]);

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
