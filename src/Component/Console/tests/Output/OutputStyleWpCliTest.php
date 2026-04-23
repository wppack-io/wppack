<?php

/*
 * This file is part of the WPPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WPPack\Component\Console\Tests\Output;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Console\Output\OutputStyle;
use WPPack\Component\Console\Output\WpCliOutput;

#[CoversClass(OutputStyle::class)]
final class OutputStyleWpCliTest extends TestCase
{
    /** @var resource|null */
    private mixed $streamFilter = null;

    protected function setUp(): void
    {
        StdoutCaptureFilter::$buffer = '';

        if (!in_array('wppack.stdout_capture', stream_get_filters(), true)) {
            stream_filter_register('wppack.stdout_capture', StdoutCaptureFilter::class);
        }

        $this->streamFilter = stream_filter_append(\STDOUT, 'wppack.stdout_capture');

        ob_start();
    }

    protected function tearDown(): void
    {
        ob_end_clean();

        if ($this->streamFilter !== null) {
            stream_filter_remove($this->streamFilter);
            $this->streamFilter = null;
        }
    }

    #[Test]
    public function successCallsWpCliSuccess(): void
    {
        $style = new OutputStyle(new WpCliOutput());

        // WP_CLI::success() requires logger; without one it's a no-op, so
        // we only check the call path doesn't throw.
        $this->expectNotToPerformAssertions();
        $style->success('All done');
    }

    #[Test]
    public function warningCallsWpCliWarning(): void
    {
        $style = new OutputStyle(new WpCliOutput());

        // WP_CLI::warning() requires logger; without one it's a no-op.
        $this->expectNotToPerformAssertions();
        $style->warning('Watch out');
    }

    #[Test]
    public function infoCallsWpCliLog(): void
    {
        $style = new OutputStyle(new WpCliOutput());

        $this->expectNotToPerformAssertions();
        $style->info('Processing...');
    }

    #[Test]
    public function lineCallsWriteln(): void
    {
        $style = new OutputStyle(new WpCliOutput());

        $this->expectNotToPerformAssertions();
        $style->line('plain text');
    }

    #[Test]
    public function newLineCallsOutputNewLine(): void
    {
        $style = new OutputStyle(new WpCliOutput());

        $this->expectNotToPerformAssertions();
        $style->newLine(2);
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

        $this->expectNotToPerformAssertions();
        $style->table(['Name', 'Age'], [['Alice', '30'], ['Bob', '25']]);
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
