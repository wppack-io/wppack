<?php

/*
 * This file is part of the WPPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WPPack\Component\Hook\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\Hook\Attribute\Action\InitAction;
use WPPack\Component\Hook\Attribute\AsHookSubscriber;
use WPPack\Component\Hook\DependencyInjection\HookServiceProvider;
use WPPack\Component\Hook\DependencyInjection\RegisterHookSubscribersPass;
use WPPack\Component\Hook\HookDiscovery;
use WPPack\Component\Hook\HookRegistry;

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
