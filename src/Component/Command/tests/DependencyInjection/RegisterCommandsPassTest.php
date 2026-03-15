<?php

declare(strict_types=1);

namespace WpPack\Component\Command\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Command\AbstractCommand;
use WpPack\Component\Command\Attribute\AsCommand;
use WpPack\Component\Command\CommandRegistry;
use WpPack\Component\Command\DependencyInjection\RegisterCommandsPass;
use WpPack\Component\Command\Input\InputInterface;
use WpPack\Component\Command\Output\OutputStyle;
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
