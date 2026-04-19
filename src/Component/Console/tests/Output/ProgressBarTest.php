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

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Console\Output\BufferedOutput;
use WPPack\Component\Console\Output\ProgressBar;

final class ProgressBarTest extends TestCase
{
    #[Test]
    public function initialState(): void
    {
        $output = new BufferedOutput();
        $bar = new ProgressBar($output, 10, 'Loading');

        self::assertSame(0, $bar->getCurrent());
        self::assertSame(10, $bar->getTotal());
        self::assertStringContainsString('0/10 (0%)', $output->getBuffer());
    }

    #[Test]
    public function advance(): void
    {
        $output = new BufferedOutput();
        $bar = new ProgressBar($output, 5);

        $bar->advance();
        self::assertSame(1, $bar->getCurrent());

        $bar->advance(2);
        self::assertSame(3, $bar->getCurrent());

        $content = $output->getBuffer();
        self::assertStringContainsString('1/5', $content);
        self::assertStringContainsString('3/5', $content);
    }

    #[Test]
    public function finish(): void
    {
        $output = new BufferedOutput();
        $bar = new ProgressBar($output, 3);

        $bar->finish();

        self::assertSame(3, $bar->getCurrent());
        self::assertStringContainsString('3/3 (100%)', $output->getBuffer());
    }

    #[Test]
    public function advanceBeyondTotalClampsPercentage(): void
    {
        $output = new BufferedOutput();
        $bar = new ProgressBar($output, 2);

        $bar->advance(5);

        self::assertSame(5, $bar->getCurrent());
        // Percentage should be clamped to 100%
        self::assertStringContainsString('(100%)', $output->getBuffer());
        self::assertStringNotContainsString('(250%)', $output->getBuffer());
    }

    #[Test]
    public function zeroTotal(): void
    {
        $output = new BufferedOutput();
        $bar = new ProgressBar($output, 0);

        self::assertSame(0, $bar->getTotal());
        self::assertStringContainsString('0/0 (0%)', $output->getBuffer());

        $bar->finish();
        self::assertStringContainsString('0/0 (0%)', $output->getBuffer());
    }

    #[Test]
    public function customMessage(): void
    {
        $output = new BufferedOutput();
        new ProgressBar($output, 5, 'Importing');

        self::assertStringContainsString('Importing:', $output->getBuffer());
    }
}
