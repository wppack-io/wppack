<?php

declare(strict_types=1);

namespace WpPack\Component\Console\Tests\Output;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Console\Output\OutputInterface;
use WpPack\Component\Console\Output\WpCliOutput;

#[CoversClass(WpCliOutput::class)]
final class WpCliOutputIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        require_once __DIR__ . '/../Fixtures/WpCliStub.php';
        \WP_CLI::reset();
    }

    #[Test]
    public function implementsOutputInterface(): void
    {
        $output = new WpCliOutput();

        self::assertInstanceOf(OutputInterface::class, $output);
    }

    #[Test]
    public function writeCallsWpCliOut(): void
    {
        $output = new WpCliOutput();

        $output->write('hello');

        self::assertSame(['hello'], \WP_CLI::$output);
    }

    #[Test]
    public function writelnCallsWpCliLog(): void
    {
        $output = new WpCliOutput();

        $output->writeln('hello line');

        self::assertSame(['hello line'], \WP_CLI::$logged);
    }

    #[Test]
    public function newLineCallsWpCliLog(): void
    {
        $output = new WpCliOutput();

        $output->newLine(3);

        self::assertCount(3, \WP_CLI::$logged);
        self::assertSame('', \WP_CLI::$logged[0]);
        self::assertSame('', \WP_CLI::$logged[1]);
        self::assertSame('', \WP_CLI::$logged[2]);
    }

    #[Test]
    public function newLineDefault(): void
    {
        $output = new WpCliOutput();

        $output->newLine();

        self::assertCount(1, \WP_CLI::$logged);
    }

    #[Test]
    public function multipleWriteCalls(): void
    {
        $output = new WpCliOutput();

        $output->write('first');
        $output->write('second');

        self::assertSame(['first', 'second'], \WP_CLI::$output);
    }
}
