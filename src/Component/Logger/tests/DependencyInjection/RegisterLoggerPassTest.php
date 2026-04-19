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

namespace WPPack\Component\Logger\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\DependencyInjection\Reference;
use WPPack\Component\Logger\Attribute\LoggerChannel;
use WPPack\Component\Logger\DependencyInjection\RegisterLoggerPass;
use WPPack\Component\Logger\LoggerFactory;

final class RegisterLoggerPassTest extends TestCase
{
    #[Test]
    public function skipsWhenLoggerFactoryNotRegistered(): void
    {
        $builder = new ContainerBuilder();
        $pass = new RegisterLoggerPass();

        $pass->process($builder);

        self::assertSame([], $builder->all());
    }

    #[Test]
    public function registersChannelLoggerForAnnotatedParameter(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(LoggerFactory::class);
        $builder->register(Fixtures\PaymentService::class);

        $pass = new RegisterLoggerPass();
        $pass->process($builder);

        self::assertTrue($builder->hasDefinition('logger.payment'));

        $loggerDefinition = $builder->findDefinition('logger.payment');
        $factory = $loggerDefinition->getFactory();
        self::assertNotNull($factory);
        self::assertInstanceOf(Reference::class, $factory[0]);
        self::assertSame(LoggerFactory::class, $factory[0]->getId());
        self::assertSame('create', $factory[1]);
        self::assertSame('payment', $loggerDefinition->getArguments()[0]);
    }

    #[Test]
    public function replacesLoggerInterfaceArgumentWithReference(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(LoggerFactory::class);
        $builder->register(Fixtures\PaymentService::class);

        $pass = new RegisterLoggerPass();
        $pass->process($builder);

        $serviceDefinition = $builder->findDefinition(Fixtures\PaymentService::class);
        $arguments = $serviceDefinition->getArguments();
        self::assertArrayHasKey(0, $arguments);
        self::assertInstanceOf(Reference::class, $arguments[0]);
        self::assertSame('logger.payment', $arguments[0]->getId());
    }

    #[Test]
    public function doesNotDuplicateChannelLogger(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(LoggerFactory::class);
        $builder->register(Fixtures\PaymentService::class);
        $builder->register(Fixtures\PaymentGateway::class);

        $pass = new RegisterLoggerPass();
        $pass->process($builder);

        $definitions = $builder->all();
        $paymentLoggerCount = 0;
        foreach ($definitions as $id => $def) {
            if ($id === 'logger.payment') {
                ++$paymentLoggerCount;
            }
        }
        self::assertSame(1, $paymentLoggerCount);
    }

    #[Test]
    public function handlesMultipleChannels(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(LoggerFactory::class);
        $builder->register(Fixtures\PaymentService::class);
        $builder->register(Fixtures\SecurityService::class);

        $pass = new RegisterLoggerPass();
        $pass->process($builder);

        self::assertTrue($builder->hasDefinition('logger.payment'));
        self::assertTrue($builder->hasDefinition('logger.security'));
    }

    #[Test]
    public function handlesMultipleLoggerParametersInSameService(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(LoggerFactory::class);
        $builder->register(Fixtures\MultiLoggerService::class);

        $pass = new RegisterLoggerPass();
        $pass->process($builder);

        self::assertTrue($builder->hasDefinition('logger.payment'));
        self::assertTrue($builder->hasDefinition('logger.audit'));

        $definition = $builder->findDefinition(Fixtures\MultiLoggerService::class);
        $arguments = $definition->getArguments();
        self::assertInstanceOf(Reference::class, $arguments[0]);
        self::assertSame('logger.payment', $arguments[0]->getId());
        self::assertInstanceOf(Reference::class, $arguments[1]);
        self::assertSame('logger.audit', $arguments[1]->getId());
    }

    #[Test]
    public function skipsServiceWithoutConstructor(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(LoggerFactory::class);
        $builder->register(Fixtures\NoConstructorService::class);

        $pass = new RegisterLoggerPass();
        $pass->process($builder);

        $definitions = $builder->all();
        self::assertCount(2, $definitions); // LoggerFactory + NoConstructorService only
    }

    #[Test]
    public function discoversChannelWhenServiceIdIsNotClassName(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(LoggerFactory::class);
        $builder->register('app.payment', Fixtures\PaymentService::class);

        $pass = new RegisterLoggerPass();
        $pass->process($builder);

        self::assertTrue($builder->hasDefinition('logger.payment'));

        $definition = $builder->findDefinition('app.payment');
        $arguments = $definition->getArguments();
        self::assertArrayHasKey(0, $arguments);
        self::assertInstanceOf(Reference::class, $arguments[0]);
        self::assertSame('logger.payment', $arguments[0]->getId());
    }

    #[Test]
    public function ignoresServicesWithoutLoggerChannel(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(LoggerFactory::class);
        $builder->register(Fixtures\PlainService::class);

        $pass = new RegisterLoggerPass();
        $pass->process($builder);

        $definitions = $builder->all();
        self::assertCount(2, $definitions); // LoggerFactory + PlainService only
    }
}
