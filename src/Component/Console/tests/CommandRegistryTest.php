<?php

declare(strict_types=1);

namespace WpPack\Component\Console\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Console\AbstractCommand;
use WpPack\Component\Console\Attribute\AsCommand;
use WpPack\Component\Console\CommandRegistry;
use WpPack\Component\Console\Exception\LogicException;
use WpPack\Component\Console\Input\InputInterface;
use WpPack\Component\Console\Output\OutputStyle;

final class CommandRegistryTest extends TestCase
{
    #[Test]
    public function addCommand(): void
    {
        $registry = new CommandRegistry();
        $command = new DummyCommand();

        $registry->add($command);

        self::assertCount(1, $registry->all());
        self::assertSame($command, $registry->all()[0]);
    }

    #[Test]
    public function addMultipleCommands(): void
    {
        $registry = new CommandRegistry();
        $registry->add(new DummyCommand());
        $registry->add(new AnotherDummyCommand());

        self::assertCount(2, $registry->all());
    }

    #[Test]
    public function registerPreventsDoubleRegistration(): void
    {
        $registry = new CommandRegistry();
        $registry->add(new DummyCommand());

        $registry->register();
        $registry->register();

        self::assertCount(1, $registry->all());
    }

    #[Test]
    public function registerIsIdempotent(): void
    {
        $registry = new CommandRegistry();
        $registry->add(new DummyCommand());

        $registry->register();

        // Second call should silently return without error
        $registry->register();

        // Commands remain accessible
        self::assertCount(1, $registry->all());
    }

    #[Test]
    public function cannotAddAfterRegister(): void
    {
        $registry = new CommandRegistry();
        $registry->register();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Cannot add commands after register() has been called.');

        $registry->add(new DummyCommand());
    }

    #[Test]
    public function addAfterRegisterThrowsEvenWithCommands(): void
    {
        $registry = new CommandRegistry();
        $registry->add(new DummyCommand());
        $registry->register();

        $this->expectException(LogicException::class);

        $registry->add(new AnotherDummyCommand());
    }

    #[Test]
    public function allReturnsEmptyByDefault(): void
    {
        $registry = new CommandRegistry();

        self::assertSame([], $registry->all());
    }

    #[Test]
    public function registerWithEmptyCommands(): void
    {
        $registry = new CommandRegistry();
        $registry->register();

        // After registration with no commands, all() still returns empty
        self::assertSame([], $registry->all());
    }

    #[Test]
    public function registerWithWpCli(): void
    {
        if (!class_exists(\WP_CLI::class, false)) {
            self::markTestSkipped('WP-CLI is not available.');
        }

        $registry = new CommandRegistry();
        $registry->add(new DummyCommand());

        // Should register with WP_CLI::add_command
        $registry->register();

        self::assertCount(1, $registry->all());
    }

    #[Test]
    public function registerSetsRegisteredFlagWithoutWpCli(): void
    {
        // Without WP_CLI, register() should mark as registered and return early
        $registry = new CommandRegistry();
        $registry->add(new DummyCommand());

        $registry->register();

        // Verify it's marked as registered by trying to add after
        $this->expectException(LogicException::class);
        $registry->add(new AnotherDummyCommand());
    }

    #[Test]
    public function allPreservesInsertionOrder(): void
    {
        $registry = new CommandRegistry();
        $first = new DummyCommand();
        $second = new AnotherDummyCommand();

        $registry->add($first);
        $registry->add($second);

        $all = $registry->all();
        self::assertSame($first, $all[0]);
        self::assertSame($second, $all[1]);
    }
}

#[AsCommand(name: 'test dummy', description: 'A dummy command')]
final class DummyCommand extends AbstractCommand
{
    protected function execute(InputInterface $input, OutputStyle $output): int
    {
        return self::SUCCESS;
    }
}

#[AsCommand(name: 'test another', description: 'Another dummy')]
final class AnotherDummyCommand extends AbstractCommand
{
    protected function execute(InputInterface $input, OutputStyle $output): int
    {
        return self::SUCCESS;
    }
}
