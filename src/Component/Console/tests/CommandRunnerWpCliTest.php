<?php

declare(strict_types=1);

namespace WpPack\Component\Console\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Console\AbstractCommand;
use WpPack\Component\Console\Attribute\AsCommand;
use WpPack\Component\Console\CommandRunner;
use WpPack\Component\Console\Input\InputArgument;
use WpPack\Component\Console\Input\InputDefinition;
use WpPack\Component\Console\Input\InputInterface;
use WpPack\Component\Console\Input\InputOption;
use WpPack\Component\Console\Output\OutputStyle;

#[CoversClass(CommandRunner::class)]
final class CommandRunnerWpCliTest extends TestCase
{
    protected function setUp(): void
    {
        require_once __DIR__ . '/Fixtures/WpCliStub.php';
        \WP_CLI::reset();
    }

    #[Test]
    public function invokeSuccessDoesNotHalt(): void
    {
        $command = new WpCliRunnerSuccessCommand();
        $runner = new CommandRunner($command);

        $runner(['world'], ['shout' => '']);

        self::assertNull(\WP_CLI::$haltedCode);
    }

    #[Test]
    public function invokeFailureCallsHalt(): void
    {
        $command = new WpCliRunnerFailCommand();
        $runner = new CommandRunner($command);

        $runner([], []);

        self::assertSame(AbstractCommand::FAILURE, \WP_CLI::$haltedCode);
    }

    #[Test]
    public function invokeWithInvalidExitCodeCallsHalt(): void
    {
        $command = new WpCliRunnerInvalidCommand();
        $runner = new CommandRunner($command);

        $runner([], []);

        self::assertSame(AbstractCommand::INVALID, \WP_CLI::$haltedCode);
    }

    #[Test]
    public function invokePassesInputToCommand(): void
    {
        $command = new WpCliRunnerSuccessCommand();
        $runner = new CommandRunner($command);

        $runner(['Alice'], []);

        // The command calls $output->success("Hello Alice!")
        // WpCliOutput is used, so WP_CLI::success() is called
        self::assertContains('Hello Alice!', \WP_CLI::$successes);
    }
}

#[AsCommand(name: 'wpcli-runner success', description: 'Success command')]
final class WpCliRunnerSuccessCommand extends AbstractCommand
{
    protected function configure(InputDefinition $definition): void
    {
        $definition
            ->addArgument(new InputArgument('name', InputArgument::REQUIRED))
            ->addOption(new InputOption('shout', InputOption::VALUE_NONE));
    }

    protected function execute(InputInterface $input, OutputStyle $output): int
    {
        $name = $input->getArgument('name');
        $output->success("Hello {$name}!");

        return self::SUCCESS;
    }
}

#[AsCommand(name: 'wpcli-runner fail', description: 'Failing command')]
final class WpCliRunnerFailCommand extends AbstractCommand
{
    protected function execute(InputInterface $input, OutputStyle $output): int
    {
        return self::FAILURE;
    }
}

#[AsCommand(name: 'wpcli-runner invalid', description: 'Invalid command')]
final class WpCliRunnerInvalidCommand extends AbstractCommand
{
    protected function execute(InputInterface $input, OutputStyle $output): int
    {
        return self::INVALID;
    }
}
