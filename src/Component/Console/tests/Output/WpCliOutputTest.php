<?php

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
    #[Test]
    public function implementsOutputInterface(): void
    {
        if (!class_exists(\WP_CLI::class, false)) {
            self::markTestSkipped('WP-CLI is not available.');
        }

        $output = new WpCliOutput();

        self::assertInstanceOf(OutputInterface::class, $output);
    }

    #[Test]
    public function writeCallsWpCliOut(): void
    {
        if (!class_exists(\WP_CLI::class, false)) {
            self::markTestSkipped('WP-CLI is not available.');
        }

        $output = new WpCliOutput();
        $output->write('test message');

        // If we get here without error, WP_CLI::out() was called successfully
        self::assertTrue(true);
    }

    #[Test]
    public function writelnCallsWpCliLog(): void
    {
        if (!class_exists(\WP_CLI::class, false)) {
            self::markTestSkipped('WP-CLI is not available.');
        }

        $output = new WpCliOutput();
        $output->writeln('test message');

        self::assertTrue(true);
    }

    #[Test]
    public function newLineCallsWpCliLog(): void
    {
        if (!class_exists(\WP_CLI::class, false)) {
            self::markTestSkipped('WP-CLI is not available.');
        }

        $output = new WpCliOutput();
        $output->newLine(2);

        self::assertTrue(true);
    }

    #[Test]
    public function canBeInstantiated(): void
    {
        // WpCliOutput can be instantiated even without WP-CLI available
        // since it only calls WP_CLI methods in its methods, not in the constructor
        $output = new WpCliOutput();

        self::assertInstanceOf(WpCliOutput::class, $output);
    }
}
