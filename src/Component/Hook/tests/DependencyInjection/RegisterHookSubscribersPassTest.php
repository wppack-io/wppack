<?php

declare(strict_types=1);

namespace WpPack\Component\Hook\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\Hook\Attribute\Action\InitAction;
use WpPack\Component\Hook\Attribute\AsHookSubscriber;
use WpPack\Component\Hook\DependencyInjection\HookServiceProvider;
use WpPack\Component\Hook\DependencyInjection\RegisterHookSubscribersPass;
use WpPack\Component\Hook\HookDiscovery;
use WpPack\Component\Hook\HookRegistry;

final class RegisterHookSubscribersPassTest extends TestCase
{
    #[Test]
    public function processesTaggedServices(): void
    {
        $builder = new ContainerBuilder();
        (new HookServiceProvider())->register($builder);

        $builder->register(TaggedSubscriber::class)
            ->addTag(RegisterHookSubscribersPass::TAG);

        $pass = new RegisterHookSubscribersPass();
        $pass->process($builder);

        $definition = $builder->findDefinition(HookDiscovery::class);
        $calls = $definition->getMethodCalls();

        $registerCalls = array_filter($calls, static fn(array $call): bool => $call['method'] === 'register');
        self::assertNotEmpty($registerCalls);
    }

    #[Test]
    public function processesAttributeMarkedServices(): void
    {
        $builder = new ContainerBuilder();
        (new HookServiceProvider())->register($builder);

        $builder->register(AttributeSubscriber::class);

        $pass = new RegisterHookSubscribersPass();
        $pass->process($builder);

        $definition = $builder->findDefinition(HookDiscovery::class);
        $calls = $definition->getMethodCalls();

        $registerCalls = array_filter($calls, static fn(array $call): bool => $call['method'] === 'register');
        self::assertNotEmpty($registerCalls);
    }

    #[Test]
    public function addsRegisterCallToRegistry(): void
    {
        $builder = new ContainerBuilder();
        (new HookServiceProvider())->register($builder);

        $pass = new RegisterHookSubscribersPass();
        $pass->process($builder);

        $definition = $builder->findDefinition(HookRegistry::class);
        $calls = $definition->getMethodCalls();

        $registerCalls = array_filter($calls, static fn(array $call): bool => $call['method'] === 'register');
        self::assertNotEmpty($registerCalls);
    }

    #[Test]
    public function skipsWhenDiscoveryNotRegistered(): void
    {
        $builder = new ContainerBuilder();

        $pass = new RegisterHookSubscribersPass();
        $pass->process($builder);

        // Should not throw
        self::assertFalse($builder->hasDefinition(HookDiscovery::class));
    }

    #[Test]
    public function skipsWhenRegistryNotRegistered(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(HookDiscovery::class);

        $pass = new RegisterHookSubscribersPass();
        $pass->process($builder);

        // Should not throw
        self::assertTrue($builder->hasDefinition(HookDiscovery::class));
    }

    #[Test]
    public function ignoresNonSubscriberServices(): void
    {
        $builder = new ContainerBuilder();
        (new HookServiceProvider())->register($builder);

        $builder->register(NonSubscriber::class);

        $pass = new RegisterHookSubscribersPass();
        $pass->process($builder);

        $definition = $builder->findDefinition(HookDiscovery::class);
        $calls = $definition->getMethodCalls();

        $registerCalls = array_filter($calls, static fn(array $call): bool => $call['method'] === 'register');
        self::assertEmpty($registerCalls);
    }

    #[Test]
    public function skipsNonExistentClass(): void
    {
        $builder = new ContainerBuilder();
        (new HookServiceProvider())->register($builder);

        $builder->register('non_existent_service', 'NonExistent\\FakeSubscriber');

        $pass = new RegisterHookSubscribersPass();
        $pass->process($builder);

        $definition = $builder->findDefinition(HookDiscovery::class);
        $calls = $definition->getMethodCalls();

        $registerCalls = array_filter($calls, static fn(array $call): bool => $call['method'] === 'register');
        self::assertEmpty($registerCalls);
    }
}

// Test fixtures

class TaggedSubscriber
{
    #[InitAction]
    public function onInit(): void {}
}

#[AsHookSubscriber]
class AttributeSubscriber
{
    #[InitAction]
    public function onInit(): void {}
}

class NonSubscriber
{
    public function doSomething(): void {}
}
