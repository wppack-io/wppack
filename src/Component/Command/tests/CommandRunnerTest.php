<?php

declare(strict_types=1);

namespace WpPack\Component\Command\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Command\AbstractCommand;
use WpPack\Component\Command\Attribute\AsCommand;
use WpPack\Component\Command\CommandRunner;
use WpPack\Component\Command\Input\InputArgument;
use WpPack\Component\Command\Input\InputDefinition;
use WpPack\Component\Command\Input\InputInterface;
use WpPack\Component\Command\Input\InputOption;
use WpPack\Component\Command\Output\OutputStyle;

final class CommandRunnerTest extends TestCase
{
    #[Test]
    public function constructsWithCommand(): void
    {
        $command = new RunnerTestCommand();
        $runner = new CommandRunner($command);

        self::assertInstanceOf(CommandRunner::class, $runner);
    }

    #[Test]
    public function invokeRequiresWpCli(): void
    {
        if (!class_exists(\WP_CLI::class, false)) {
            self::markTestSkipped('WP-CLI is not available.');
        }

        $command = new RunnerTestCommand();
        $runner = new CommandRunner($command);
        $runner(['world'], ['shout' => '']);
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
