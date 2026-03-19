<?php

declare(strict_types=1);

namespace WpPack\Component\Console\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Console\AbstractCommand;
use WpPack\Component\Console\Attribute\AsCommand;
use WpPack\Component\Console\CommandRegistry;
use WpPack\Component\Console\CommandRunner;
use WpPack\Component\Console\Input\InputArgument;
use WpPack\Component\Console\Input\InputDefinition;
use WpPack\Component\Console\Input\InputInterface;
use WpPack\Component\Console\Input\InputOption;
use WpPack\Component\Console\Output\OutputStyle;

#[CoversClass(CommandRegistry::class)]
final class CommandRegistryWpCliTest extends TestCase
{
    protected function setUp(): void
    {
        require_once __DIR__ . '/Fixtures/WpCliStub.php';
        \WP_CLI::reset();
    }

    #[Test]
    public function registerWithWpCliAddsCommand(): void
    {
        $registry = new CommandRegistry();
        $registry->add(new RegistryWpCliTestCommand());

        $registry->register();

        self::assertCount(1, \WP_CLI::$registeredCommands);
        self::assertSame('wpcli-registry test', \WP_CLI::$registeredCommands[0]['command']);
    }

    #[Test]
    public function registerPassesDescription(): void
    {
        $registry = new CommandRegistry();
        $registry->add(new RegistryWpCliTestCommand());

        $registry->register();

        $args = \WP_CLI::$registeredCommands[0]['args'];
        self::assertSame('Test command for WP_CLI', $args['shortdesc']);
    }

    #[Test]
    public function registerPassesSynopsis(): void
    {
        $registry = new CommandRegistry();
        $registry->add(new RegistryWpCliTestCommand());

        $registry->register();

        $args = \WP_CLI::$registeredCommands[0]['args'];
        self::assertIsArray($args['synopsis']);
        self::assertNotEmpty($args['synopsis']);
    }

    #[Test]
    public function registerPassesUsageAsLongdesc(): void
    {
        $registry = new CommandRegistry();
        $registry->add(new RegistryWpCliTestWithUsageCommand());

        $registry->register();

        $args = \WP_CLI::$registeredCommands[0]['args'];
        self::assertSame('wp wpcli-registry usage <name>', $args['longdesc']);
    }

    #[Test]
    public function registerOmitsLongdescWhenUsageEmpty(): void
    {
        $registry = new CommandRegistry();
        $registry->add(new RegistryWpCliTestCommand());

        $registry->register();

        $args = \WP_CLI::$registeredCommands[0]['args'];
        self::assertArrayNotHasKey('longdesc', $args);
    }

    #[Test]
    public function registerCreatesCommandRunner(): void
    {
        $registry = new CommandRegistry();
        $registry->add(new RegistryWpCliTestCommand());

        $registry->register();

        $callable = \WP_CLI::$registeredCommands[0]['callable'];
        self::assertInstanceOf(CommandRunner::class, $callable);
    }

    #[Test]
    public function registerMultipleCommands(): void
    {
        $registry = new CommandRegistry();
        $registry->add(new RegistryWpCliTestCommand());
        $registry->add(new RegistryWpCliTestWithUsageCommand());

        $registry->register();

        self::assertCount(2, \WP_CLI::$registeredCommands);
    }

    #[Test]
    public function registerIsIdempotent(): void
    {
        $registry = new CommandRegistry();
        $registry->add(new RegistryWpCliTestCommand());

        $registry->register();
        $registry->register();

        // Only registered once
        self::assertCount(1, \WP_CLI::$registeredCommands);
    }
}

#[AsCommand(name: 'wpcli-registry test', description: 'Test command for WP_CLI')]
final class RegistryWpCliTestCommand extends AbstractCommand
{
    protected function configure(InputDefinition $definition): void
    {
        $definition
            ->addArgument(new InputArgument('name', InputArgument::REQUIRED, 'Name'))
            ->addOption(new InputOption('loud', InputOption::VALUE_NONE, 'Shout'));
    }

    protected function execute(InputInterface $input, OutputStyle $output): int
    {
        return self::SUCCESS;
    }
}

#[AsCommand(name: 'wpcli-registry usage', description: 'Command with usage', usage: 'wp wpcli-registry usage <name>')]
final class RegistryWpCliTestWithUsageCommand extends AbstractCommand
{
    protected function execute(InputInterface $input, OutputStyle $output): int
    {
        return self::SUCCESS;
    }
}
