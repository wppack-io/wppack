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

namespace WpPack\Component\Mailer\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\Reference;
use WpPack\Component\DependencyInjection\ServiceProviderInterface;
use WpPack\Component\Mailer\DependencyInjection\MailerServiceProvider;
use WpPack\Component\Mailer\DependencyInjection\RegisterTransportFactoriesPass;
use WpPack\Component\Mailer\Mailer;
use WpPack\Component\Mailer\Transport\NativeTransportFactory;
use WpPack\Component\Mailer\Transport\Transport;
use WpPack\Component\Mailer\Transport\TransportInterface;

final class MailerServiceProviderTest extends TestCase
{
    #[Test]
    public function implementsServiceProviderInterface(): void
    {
        $provider = new MailerServiceProvider();

        self::assertInstanceOf(ServiceProviderInterface::class, $provider);
    }

    #[Test]
    public function registersNativeTransportFactory(): void
    {
        $builder = new ContainerBuilder();
        $provider = new MailerServiceProvider();

        $provider->register($builder);

        self::assertTrue($builder->hasDefinition(NativeTransportFactory::class));
    }

    #[Test]
    public function nativeTransportFactoryIsTagged(): void
    {
        $builder = new ContainerBuilder();
        $provider = new MailerServiceProvider();

        $provider->register($builder);

        $definition = $builder->findDefinition(NativeTransportFactory::class);
        self::assertTrue($definition->hasTag('mailer.transport_factory'));
    }

    #[Test]
    public function registersTransport(): void
    {
        $builder = new ContainerBuilder();
        $provider = new MailerServiceProvider();

        $provider->register($builder);

        self::assertTrue($builder->hasDefinition(Transport::class));
    }

    #[Test]
    public function registersTransportInterface(): void
    {
        $builder = new ContainerBuilder();
        $provider = new MailerServiceProvider();

        $provider->register($builder);

        self::assertTrue($builder->hasDefinition(TransportInterface::class));
    }

    #[Test]
    public function transportInterfaceUsesFactoryMethod(): void
    {
        $builder = new ContainerBuilder();
        $provider = new MailerServiceProvider();

        $provider->register($builder);

        $definition = $builder->findDefinition(TransportInterface::class);
        $factory = $definition->getFactory();
        self::assertNotNull($factory);
        self::assertInstanceOf(Reference::class, $factory[0]);
        self::assertSame('fromString', $factory[1]);
    }

    #[Test]
    public function defaultDsnIsNative(): void
    {
        $builder = new ContainerBuilder();
        $provider = new MailerServiceProvider();

        $provider->register($builder);

        $definition = $builder->findDefinition(TransportInterface::class);
        $arguments = $definition->getArguments();
        self::assertSame('native://default', $arguments[0]);
    }

    #[Test]
    public function customDsnIsApplied(): void
    {
        $builder = new ContainerBuilder();
        $provider = new MailerServiceProvider(dsn: 'ses+https://default');

        $provider->register($builder);

        $definition = $builder->findDefinition(TransportInterface::class);
        $arguments = $definition->getArguments();
        self::assertSame('ses+https://default', $arguments[0]);
    }

    #[Test]
    public function registersMailer(): void
    {
        $builder = new ContainerBuilder();
        $provider = new MailerServiceProvider();

        $provider->register($builder);

        self::assertTrue($builder->hasDefinition(Mailer::class));
    }

    #[Test]
    public function canBeAddedViaContainerBuilder(): void
    {
        $builder = new ContainerBuilder();

        $result = $builder->addServiceProvider(new MailerServiceProvider());

        self::assertSame($builder, $result);
        self::assertTrue($builder->hasDefinition(NativeTransportFactory::class));
        self::assertTrue($builder->hasDefinition(Transport::class));
        self::assertTrue($builder->hasDefinition(TransportInterface::class));
        self::assertTrue($builder->hasDefinition(Mailer::class));
    }

    #[Test]
    public function worksWithRegisterTransportFactoriesPass(): void
    {
        $builder = new ContainerBuilder();
        $builder->addServiceProvider(new MailerServiceProvider());
        $builder->addCompilerPass(new RegisterTransportFactoriesPass());

        $container = $builder->compile();

        self::assertTrue($container->has(Mailer::class));
        self::assertInstanceOf(Mailer::class, $container->get(Mailer::class));
    }
}
