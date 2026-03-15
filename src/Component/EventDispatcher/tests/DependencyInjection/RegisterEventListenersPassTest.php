<?php

declare(strict_types=1);

namespace WpPack\Component\EventDispatcher\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\EventDispatcher\Attribute\AsEventListener;
use WpPack\Component\EventDispatcher\DependencyInjection\EventDispatcherServiceProvider;
use WpPack\Component\EventDispatcher\DependencyInjection\RegisterEventListenersPass;
use WpPack\Component\EventDispatcher\Event;
use WpPack\Component\EventDispatcher\EventDispatcher;
use WpPack\Component\EventDispatcher\EventSubscriberInterface;

final class RegisterEventListenersPassTest extends TestCase
{
    #[Test]
    public function processesAttributeListenersOnClass(): void
    {
        $builder = new ContainerBuilder();
        (new EventDispatcherServiceProvider())->register($builder);

        $builder->register(ClassLevelListener::class);

        $pass = new RegisterEventListenersPass();
        $pass->process($builder);

        $definition = $builder->findDefinition(EventDispatcher::class);
        $calls = $definition->getMethodCalls();

        $addListenerCalls = array_filter($calls, static fn(array $call): bool => $call['method'] === 'addListener');
        self::assertNotEmpty($addListenerCalls);
    }

    #[Test]
    public function processesAttributeListenersOnMethod(): void
    {
        $builder = new ContainerBuilder();
        (new EventDispatcherServiceProvider())->register($builder);

        $builder->register(MethodLevelListener::class);

        $pass = new RegisterEventListenersPass();
        $pass->process($builder);

        $definition = $builder->findDefinition(EventDispatcher::class);
        $calls = $definition->getMethodCalls();

        $addListenerCalls = array_filter($calls, static fn(array $call): bool => $call['method'] === 'addListener');
        self::assertNotEmpty($addListenerCalls);
    }

    #[Test]
    public function processesSubscribers(): void
    {
        $builder = new ContainerBuilder();
        (new EventDispatcherServiceProvider())->register($builder);

        $builder->register(TestSubscriber::class);

        $pass = new RegisterEventListenersPass();
        $pass->process($builder);

        $definition = $builder->findDefinition(EventDispatcher::class);
        $calls = $definition->getMethodCalls();

        $addSubscriberCalls = array_filter($calls, static fn(array $call): bool => $call['method'] === 'addSubscriber');
        self::assertNotEmpty($addSubscriberCalls);
    }

    #[Test]
    public function skipsWhenDispatcherNotRegistered(): void
    {
        $builder = new ContainerBuilder();

        $pass = new RegisterEventListenersPass();
        $pass->process($builder);

        self::assertFalse($builder->hasDefinition(EventDispatcher::class));
    }

    #[Test]
    public function ignoresServicesWithoutAttributes(): void
    {
        $builder = new ContainerBuilder();
        (new EventDispatcherServiceProvider())->register($builder);

        $builder->register(PlainService::class);

        $pass = new RegisterEventListenersPass();
        $pass->process($builder);

        $definition = $builder->findDefinition(EventDispatcher::class);
        $calls = $definition->getMethodCalls();

        $listenerCalls = array_filter($calls, static fn(array $call): bool => \in_array($call['method'], ['addListener', 'addSubscriber'], true));
        self::assertEmpty($listenerCalls);
    }

    #[Test]
    public function doesNotDoubleRegisterWhenClassHasBothAttributeAndSubscriber(): void
    {
        $builder = new ContainerBuilder();
        (new EventDispatcherServiceProvider())->register($builder);

        $builder->register(AttributeAndSubscriberListener::class);

        $pass = new RegisterEventListenersPass();
        $pass->process($builder);

        $definition = $builder->findDefinition(EventDispatcher::class);
        $calls = $definition->getMethodCalls();

        $addListenerCalls = array_filter($calls, static fn(array $call): bool => $call['method'] === 'addListener');
        $addSubscriberCalls = array_filter($calls, static fn(array $call): bool => $call['method'] === 'addSubscriber');

        // Should register via attribute only, not via subscriber
        self::assertNotEmpty($addListenerCalls);
        self::assertEmpty($addSubscriberCalls, 'Should not register as subscriber when attributes are present.');
    }

    #[Test]
    public function methodOverrideResolvesEventFromTargetMethod(): void
    {
        $builder = new ContainerBuilder();
        (new EventDispatcherServiceProvider())->register($builder);

        $builder->register(MethodOverrideListener::class);

        $pass = new RegisterEventListenersPass();
        $pass->process($builder);

        $definition = $builder->findDefinition(EventDispatcher::class);
        $calls = $definition->getMethodCalls();

        $addListenerCalls = array_filter($calls, static fn(array $call): bool => $call['method'] === 'addListener');
        self::assertNotEmpty($addListenerCalls);

        $call = array_values($addListenerCalls)[0];
        // Event should be resolved from handleEvent() parameter (AnotherTestEvent), not onEvent() parameter
        self::assertSame(AnotherTestEvent::class, $call['arguments'][0]);
        // Callback method should be handleEvent
        self::assertSame('handleEvent', $call['arguments'][1][1]);
    }

    #[Test]
    public function resolvesEventFromMethodParameter(): void
    {
        $builder = new ContainerBuilder();
        (new EventDispatcherServiceProvider())->register($builder);

        $builder->register(AutoResolveListener::class);

        $pass = new RegisterEventListenersPass();
        $pass->process($builder);

        $definition = $builder->findDefinition(EventDispatcher::class);
        $calls = $definition->getMethodCalls();

        $addListenerCalls = array_filter($calls, static fn(array $call): bool => $call['method'] === 'addListener');
        self::assertNotEmpty($addListenerCalls);

        $call = array_values($addListenerCalls)[0];
        self::assertSame(PassTestEvent::class, $call['arguments'][0]);
    }
}

// Test fixtures

class PassTestEvent extends Event {}

#[AsEventListener(event: PassTestEvent::class)]
class ClassLevelListener
{
    public function __invoke(PassTestEvent $event): void {}
}

class MethodLevelListener
{
    #[AsEventListener(event: PassTestEvent::class, priority: 5)]
    public function onEvent(PassTestEvent $event): void {}
}

class AutoResolveListener
{
    #[AsEventListener]
    public function handle(PassTestEvent $event): void {}
}

class TestSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            PassTestEvent::class => 'onEvent',
        ];
    }

    public function onEvent(PassTestEvent $event): void {}
}

class PlainService
{
    public function doWork(): void {}
}

#[AsEventListener(event: PassTestEvent::class)]
class AttributeAndSubscriberListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            PassTestEvent::class => 'onEvent',
        ];
    }

    public function __invoke(PassTestEvent $event): void {}

    public function onEvent(PassTestEvent $event): void {}
}

class MethodOverrideListener
{
    #[AsEventListener(method: 'handleEvent')]
    public function onEvent(PassTestEvent $event): void {}

    public function handleEvent(AnotherTestEvent $event): void {}
}

class AnotherTestEvent extends Event {}
