<?php

/*
 * This file is part of the WpPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WpPack\Component\Console\Tests\Output;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Console\Output\OutputInterface;
use WpPack\Component\Console\Output\WpCliOutput;

#[CoversClass(WpCliOutput::class)]
final class WpCliOutputTest extends TestCase
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
    }

    protected function tearDown(): void
    {
        if ($this->streamFilter !== null) {
            stream_filter_remove($this->streamFilter);
            $this->streamFilter = null;
        }
    }

    #[Test]
    public function implementsOutputInterface(): void
    {
        self::assertInstanceOf(OutputInterface::class, new WpCliOutput());
    }

    #[Test]
    public function writeOutputsToStdout(): void
    {
        $output = new WpCliOutput();

        $output->write('hello');

        self::assertSame('hello', StdoutCaptureFilter::$buffer);
    }

    #[Test]
    public function multipleWritesConcatenate(): void
    {
        $output = new WpCliOutput();

        $output->write('first');
        $output->write('second');

        self::assertSame('firstsecond', StdoutCaptureFilter::$buffer);
    }

    #[Test]
    public function writelnCallsWpCliLog(): void
    {
        $output = new WpCliOutput();

        // WP_CLI::log() requires a logger; without one it's a no-op
        $output->writeln('test message');

        self::assertSame('', StdoutCaptureFilter::$buffer);
    }

    #[Test]
    public function newLineCallsWpCliLog(): void
    {
        $output = new WpCliOutput();

        $output->newLine(2);

        self::assertSame('', StdoutCaptureFilter::$buffer);
    }
}

/**
 * Stream filter to capture fwrite(STDOUT) output.
 */
final class StdoutCaptureFilter extends \php_user_filter
{
    public static string $buffer = '';

    public function filter($in, $out, &$consumed, $closing): int
    {
        while ($bucket = stream_bucket_make_writeable($in)) {
            self::$buffer .= $bucket->data;
            $consumed += $bucket->datalen;
        }

        return \PSFS_PASS_ON;
    }
}
