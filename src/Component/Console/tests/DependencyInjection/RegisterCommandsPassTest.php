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

namespace WpPack\Component\Console\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Console\AbstractCommand;
use WpPack\Component\Console\Attribute\AsCommand;
use WpPack\Component\Console\CommandRegistry;
use WpPack\Component\Console\DependencyInjection\RegisterCommandsPass;
use WpPack\Component\Console\Input\InputInterface;
use WpPack\Component\Console\Output\OutputStyle;
use WpPack\Component\DependencyInjection\ContainerBuilder;

final class RegisterCommandsPassTest extends TestCase
{
    #[Test]
    public function skipsWhenRegistryNotDefined(): void
    {
        $builder = new ContainerBuilder();
        $pass = new RegisterCommandsPass();

        $pass->process($builder);

        self::assertFalse($builder->hasDefinition(CommandRegistry::class));
    }

    #[Test]
    public function detectsCommandsByAttribute(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(CommandRegistry::class);
        $builder->register(PassTestCommand::class);

        $pass = new RegisterCommandsPass();
        $pass->process($builder);

        $registryDef = $builder->findDefinition(CommandRegistry::class);
        $methodCalls = $registryDef->getMethodCalls();

        $addCalls = array_filter($methodCalls, static fn(array $call): bool => $call['method'] === 'add');
        $registerCalls = array_filter($methodCalls, static fn(array $call): bool => $call['method'] === 'register');

        self::assertCount(1, $addCalls);
        self::assertCount(1, $registerCalls);
    }

    #[Test]
    public function detectsCommandsByTag(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(CommandRegistry::class);
        $builder->register(PassTestTaggedCommand::class)->addTag(RegisterCommandsPass::TAG);

        $pass = new RegisterCommandsPass();
        $pass->process($builder);

        $registryDef = $builder->findDefinition(CommandRegistry::class);
        $methodCalls = $registryDef->getMethodCalls();

        $addCalls = array_filter($methodCalls, static fn(array $call): bool => $call['method'] === 'add');
        self::assertCount(1, $addCalls);
    }

    #[Test]
    public function ignoresNonCommandClasses(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(CommandRegistry::class);
        $builder->register(\stdClass::class);

        $pass = new RegisterCommandsPass();
        $pass->process($builder);

        $registryDef = $builder->findDefinition(CommandRegistry::class);
        $methodCalls = $registryDef->getMethodCalls();

        $addCalls = array_filter($methodCalls, static fn(array $call): bool => $call['method'] === 'add');
        self::assertCount(0, $addCalls);
    }

    #[Test]
    public function alwaysCallsRegister(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(CommandRegistry::class);

        $pass = new RegisterCommandsPass();
        $pass->process($builder);

        $registryDef = $builder->findDefinition(CommandRegistry::class);
        $methodCalls = $registryDef->getMethodCalls();

        $registerCalls = array_filter($methodCalls, static fn(array $call): bool => $call['method'] === 'register');
        self::assertCount(1, $registerCalls);
    }

    #[Test]
    public function ignoresNonExistentClasses(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(CommandRegistry::class);
        $builder->register('NonExistent\\FakeCommand');

        $pass = new RegisterCommandsPass();
        $pass->process($builder);

        $registryDef = $builder->findDefinition(CommandRegistry::class);
        $methodCalls = $registryDef->getMethodCalls();

        $addCalls = array_filter($methodCalls, static fn(array $call): bool => $call['method'] === 'add');
        self::assertCount(0, $addCalls);
    }

    #[Test]
    public function ignoresAbstractCommandSubclassWithoutAttributeOrTag(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(CommandRegistry::class);
        $builder->register(PassTestPlainSubclass::class);

        $pass = new RegisterCommandsPass();
        $pass->process($builder);

        $registryDef = $builder->findDefinition(CommandRegistry::class);
        $methodCalls = $registryDef->getMethodCalls();

        $addCalls = array_filter($methodCalls, static fn(array $call): bool => $call['method'] === 'add');
        self::assertCount(0, $addCalls);
    }

    #[Test]
    public function detectsCommandWithExplicitClass(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(CommandRegistry::class);
        $def = $builder->register('my.command');
        $def->setClass(PassTestCommand::class);

        $pass = new RegisterCommandsPass();
        $pass->process($builder);

        $registryDef = $builder->findDefinition(CommandRegistry::class);
        $methodCalls = $registryDef->getMethodCalls();

        $addCalls = array_filter($methodCalls, static fn(array $call): bool => $call['method'] === 'add');
        self::assertCount(1, $addCalls);
    }

    #[Test]
    public function tagConstant(): void
    {
        self::assertSame('console.command', RegisterCommandsPass::TAG);
    }

    #[Test]
    public function detectsMultipleCommands(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(CommandRegistry::class);
        $builder->register(PassTestCommand::class);
        $builder->register(PassTestTaggedCommand::class)->addTag(RegisterCommandsPass::TAG);

        $pass = new RegisterCommandsPass();
        $pass->process($builder);

        $registryDef = $builder->findDefinition(CommandRegistry::class);
        $methodCalls = $registryDef->getMethodCalls();

        $addCalls = array_filter($methodCalls, static fn(array $call): bool => $call['method'] === 'add');
        self::assertCount(2, $addCalls);
    }
}

#[AsCommand(name: 'test pass', description: 'Pass test command')]
final class PassTestCommand extends AbstractCommand
{
    protected function execute(InputInterface $input, OutputStyle $output): int
    {
        return self::SUCCESS;
    }
}

#[AsCommand(name: 'test pass-tagged', description: 'Tagged test command')]
final class PassTestTaggedCommand extends AbstractCommand
{
    protected function execute(InputInterface $input, OutputStyle $output): int
    {
        return self::SUCCESS;
    }
}

/**
 * A subclass of AbstractCommand without #[AsCommand] attribute and no tag.
 */
final class PassTestPlainSubclass extends AbstractCommand
{
    protected function execute(InputInterface $input, OutputStyle $output): int
    {
        return self::SUCCESS;
    }
}
