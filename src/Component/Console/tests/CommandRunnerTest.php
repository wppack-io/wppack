<?php

declare(strict_types=1);

namespace WpPack\Component\Console\Tests;

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
