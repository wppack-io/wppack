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

namespace WPPack\Component\Messenger\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\Messenger\Attribute\AsMessageHandler;
use WPPack\Component\Messenger\DependencyInjection\RegisterMessageHandlersPass;
use WPPack\Component\Messenger\Handler\HandlerLocator;

#[CoversClass(RegisterMessageHandlersPass::class)]
final class RegisterMessageHandlersPassTest extends TestCase
{
    #[Test]
    public function passNoOpWhenLocatorAbsent(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(RegisterHandlersPassTestHandler::class)->addTag('messenger.message_handler');

        (new RegisterMessageHandlersPass())->process($builder);

        self::assertFalse($builder->hasDefinition(HandlerLocator::class));
    }

    #[Test]
    public function resolvesMessageClassFromInvokeTypeHint(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(HandlerLocator::class);
        $builder->register(RegisterHandlersPassTestHandler::class)->addTag('messenger.message_handler');

        (new RegisterMessageHandlersPass())->process($builder);

        $calls = $builder->findDefinition(HandlerLocator::class)->getMethodCalls();
        self::assertCount(1, $calls);
        self::assertSame('addHandler', $calls[0]['method']);
        self::assertSame(RegisterHandlersPassTestMessage::class, $calls[0]['arguments'][0]);
    }

    #[Test]
    public function attributeHandlesOverridesInvokeSignature(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(HandlerLocator::class);
        $builder->register(RegisterHandlersPassOverrideHandler::class)->addTag('messenger.message_handler');

        (new RegisterMessageHandlersPass())->process($builder);

        $calls = $builder->findDefinition(HandlerLocator::class)->getMethodCalls();
        self::assertCount(1, $calls);
        self::assertSame(RegisterHandlersPassOtherMessage::class, $calls[0]['arguments'][0]);
    }

    #[Test]
    public function skipsHandlerWithoutInvokeMethod(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(HandlerLocator::class);
        $builder->register(RegisterHandlersPassNoInvokeHandler::class)->addTag('messenger.message_handler');

        (new RegisterMessageHandlersPass())->process($builder);

        $calls = $builder->findDefinition(HandlerLocator::class)->getMethodCalls();
        self::assertCount(0, $calls);
    }

    #[Test]
    public function skipsHandlerWithBuiltinInvokeTypeHint(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(HandlerLocator::class);
        $builder->register(RegisterHandlersPassBuiltinHandler::class)->addTag('messenger.message_handler');

        (new RegisterMessageHandlersPass())->process($builder);

        $calls = $builder->findDefinition(HandlerLocator::class)->getMethodCalls();
        self::assertCount(0, $calls);
    }

    #[Test]
    public function skipsHandlerWhenClassDoesNotExist(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(HandlerLocator::class);
        $builder->register('ghost.handler', 'WPPack\\Tests\\Nonexistent\\Ghost')
            ->addTag('messenger.message_handler');

        (new RegisterMessageHandlersPass())->process($builder);

        $calls = $builder->findDefinition(HandlerLocator::class)->getMethodCalls();
        self::assertCount(0, $calls);
    }
}

/**
 * @internal
 */
final class RegisterHandlersPassTestMessage {}

/**
 * @internal
 */
final class RegisterHandlersPassOtherMessage {}

/**
 * @internal
 */
final class RegisterHandlersPassTestHandler
{
    public function __invoke(RegisterHandlersPassTestMessage $message): void {}
}

/**
 * @internal
 */
#[AsMessageHandler(handles: RegisterHandlersPassOtherMessage::class)]
final class RegisterHandlersPassOverrideHandler
{
    public function __invoke(RegisterHandlersPassTestMessage $message): void {}
}

/**
 * @internal
 */
final class RegisterHandlersPassNoInvokeHandler
{
    public function handle(): void {}
}

/**
 * @internal
 */
final class RegisterHandlersPassBuiltinHandler
{
    public function __invoke(string $message): void {}
}
