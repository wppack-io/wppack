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

namespace WpPack\Component\Console\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WP_CLI\ExitException;
use WpPack\Component\Console\AbstractCommand;
use WpPack\Component\Console\Attribute\AsCommand;
use WpPack\Component\Console\CommandRunner;
use WpPack\Component\Console\Input\InputArgument;
use WpPack\Component\Console\Input\InputDefinition;
use WpPack\Component\Console\Input\InputInterface;
use WpPack\Component\Console\Input\InputOption;
use WpPack\Component\Console\Output\OutputStyle;
use WpPack\Component\Console\Tests\Output\StdoutCaptureFilter;

final class CommandRunnerTest extends TestCase
{
    private static \ReflectionProperty $captureExit;

    public static function setUpBeforeClass(): void
    {
        self::$captureExit = new \ReflectionProperty(\WP_CLI::class, 'capture_exit');
    }

    /** @var resource|null */
    private mixed $streamFilter = null;

    protected function setUp(): void
    {
        self::$captureExit->setValue(null, true);

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

        self::$captureExit->setValue(null, false);
    }

    #[Test]
    public function constructsWithCommand(): void
    {
        $command = new RunnerTestCommand();
        $runner = new CommandRunner($command);

        self::assertInstanceOf(CommandRunner::class, $runner);
    }

    #[Test]
    public function runnerIsCallable(): void
    {
        $command = new RunnerTestCommand();
        $runner = new CommandRunner($command);

        self::assertIsCallable($runner);
    }

    #[Test]
    public function invokeSuccessDoesNotHalt(): void
    {
        $command = new RunnerTestCommand();
        $runner = new CommandRunner($command);

        $runner(['world'], ['shout' => '']);

        // If we get here, no ExitException was thrown — halt() was not called
        self::assertTrue(true);
    }

    #[Test]
    public function invokeFailureCallsHalt(): void
    {
        $command = new RunnerFailCommand();
        $runner = new CommandRunner($command);

        $this->expectException(ExitException::class);

        $runner([], []);
    }

    #[Test]
    public function invokeWithInvalidExitCodeCallsHalt(): void
    {
        $command = new RunnerInvalidCommand();
        $runner = new CommandRunner($command);

        $this->expectException(ExitException::class);

        $runner([], []);
    }

    #[Test]
    public function invokePassesInputToCommand(): void
    {
        $command = new RunnerCaptureCommand();
        $runner = new CommandRunner($command);

        $runner(['Alice'], ['loud' => '']);

        self::assertSame('Alice', RunnerCaptureCommand::$capturedName);
        self::assertTrue(RunnerCaptureCommand::$capturedLoud);
    }
}

#[AsCommand(name: 'test runner', description: 'Runner test command')]
final class RunnerTestCommand extends AbstractCommand
{
    protected function configure(InputDefinition $definition): void
    {
        $definition
            ->addArgument(new InputArgument('name', InputArgument::REQUIRED))
            ->addOption(new InputOption('shout', InputOption::VALUE_NONE));
    }

    protected function execute(InputInterface $input, OutputStyle $output): int
    {
        return self::SUCCESS;
    }
}

#[AsCommand(name: 'test runner-fail', description: 'Failing runner test')]
final class RunnerFailCommand extends AbstractCommand
{
    protected function execute(InputInterface $input, OutputStyle $output): int
    {
        return self::FAILURE;
    }
}

#[AsCommand(name: 'test runner-invalid', description: 'Invalid exit code')]
final class RunnerInvalidCommand extends AbstractCommand
{
    protected function execute(InputInterface $input, OutputStyle $output): int
    {
        return self::INVALID;
    }
}

#[AsCommand(name: 'test runner-capture', description: 'Capture input command')]
final class RunnerCaptureCommand extends AbstractCommand
{
    public static ?string $capturedName = null;
    public static bool $capturedLoud = false;

    protected function configure(InputDefinition $definition): void
    {
        $definition
            ->addArgument(new InputArgument('name', InputArgument::REQUIRED))
            ->addOption(new InputOption('loud', InputOption::VALUE_NONE));
    }

    protected function execute(InputInterface $input, OutputStyle $output): int
    {
        self::$capturedName = $input->getArgument('name');
        self::$capturedLoud = $input->getOption('loud');

        return self::SUCCESS;
    }
}
