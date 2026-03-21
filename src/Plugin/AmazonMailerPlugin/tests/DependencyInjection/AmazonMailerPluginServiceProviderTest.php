<?php

declare(strict_types=1);

namespace WpPack\Plugin\AmazonMailerPlugin\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\Reference;
use WpPack\Component\DependencyInjection\ServiceProviderInterface;
use WpPack\Component\Mailer\Bridge\Amazon\Transport\SesTransportFactory;
use WpPack\Component\Mailer\Mailer;
use WpPack\Component\Mailer\Transport\NativeTransportFactory;
use WpPack\Component\Mailer\Transport\Transport;
use WpPack\Component\Mailer\Transport\TransportInterface;
use WpPack\Plugin\AmazonMailerPlugin\Configuration\AmazonMailerConfiguration;
use WpPack\Plugin\AmazonMailerPlugin\DependencyInjection\AmazonMailerPluginServiceProvider;
use WpPack\Plugin\AmazonMailerPlugin\Handler\BounceHandler;
use WpPack\Plugin\AmazonMailerPlugin\Handler\ComplaintHandler;
use WpPack\Plugin\AmazonMailerPlugin\Message\SesNotificationNormalizer;

#[CoversClass(AmazonMailerPluginServiceProvider::class)]
final class AmazonMailerPluginServiceProviderTest extends TestCase
{
    private ContainerBuilder $builder;
    private AmazonMailerPluginServiceProvider $provider;

    protected function setUp(): void
    {
        $this->builder = new ContainerBuilder();
        $this->provider = new AmazonMailerPluginServiceProvider();
    }

    #[Test]
    public function implementsServiceProviderInterface(): void
    {
        self::assertInstanceOf(ServiceProviderInterface::class, $this->provider);
    }

    #[Test]
    public function registersConfiguration(): void
    {
        $this->provider->register($this->builder);

        self::assertTrue($this->builder->hasDefinition(AmazonMailerConfiguration::class));

        $definition = $this->builder->findDefinition(AmazonMailerConfiguration::class);
        $factory = $definition->getFactory();
        self::assertNotNull($factory);
        self::assertSame(AmazonMailerConfiguration::class, $factory[0]);
        self::assertSame('fromEnvironment', $factory[1]);
    }

    #[Test]
    public function registersSesTransportFactory(): void
    {
        $this->provider->register($this->builder);

        self::assertTrue($this->builder->hasDefinition(SesTransportFactory::class));

        $definition = $this->builder->findDefinition(SesTransportFactory::class);
        self::assertTrue($definition->hasTag('mailer.transport_factory'));
    }

    #[Test]
    public function registersNativeTransportFactory(): void
    {
        $this->provider->register($this->builder);

        self::assertTrue($this->builder->hasDefinition(NativeTransportFactory::class));

        $definition = $this->builder->findDefinition(NativeTransportFactory::class);
        self::assertTrue($definition->hasTag('mailer.transport_factory'));
    }

    #[Test]
    public function registersTransport(): void
    {
        $this->provider->register($this->builder);

        self::assertTrue($this->builder->hasDefinition(Transport::class));
    }

    #[Test]
    public function registersTransportInterface(): void
    {
        $this->provider->register($this->builder);

        self::assertTrue($this->builder->hasDefinition(TransportInterface::class));

        $definition = $this->builder->findDefinition(TransportInterface::class);
        $factory = $definition->getFactory();
        self::assertNotNull($factory);
        self::assertInstanceOf(Reference::class, $factory[0]);
        self::assertSame(Transport::class, (string) $factory[0]);
        self::assertSame('fromString', $factory[1]);
    }

    #[Test]
    public function registersMailer(): void
    {
        $this->provider->register($this->builder);

        self::assertTrue($this->builder->hasDefinition(Mailer::class));

        $definition = $this->builder->findDefinition(Mailer::class);
        $arguments = $definition->getArguments();
        self::assertCount(1, $arguments);
        self::assertInstanceOf(Reference::class, $arguments[0]);
        self::assertSame(TransportInterface::class, (string) $arguments[0]);
    }

    #[Test]
    public function registersSesNotificationNormalizer(): void
    {
        $this->provider->register($this->builder);

        self::assertTrue($this->builder->hasDefinition(SesNotificationNormalizer::class));
    }

    #[Test]
    public function registersBounceHandler(): void
    {
        $this->provider->register($this->builder);

        self::assertTrue($this->builder->hasDefinition(BounceHandler::class));

        $definition = $this->builder->findDefinition(BounceHandler::class);
        self::assertTrue($definition->hasTag('messenger.message_handler'));
    }

    #[Test]
    public function registersComplaintHandler(): void
    {
        $this->provider->register($this->builder);

        self::assertTrue($this->builder->hasDefinition(ComplaintHandler::class));

        $definition = $this->builder->findDefinition(ComplaintHandler::class);
        self::assertTrue($definition->hasTag('messenger.message_handler'));
    }

    #[Test]
    public function extractDsnReturnsDsnString(): void
    {
        $config = new AmazonMailerConfiguration(dsn: 'ses+api://default?region=us-east-1');

        $dsn = AmazonMailerPluginServiceProvider::extractDsn($config);

        self::assertSame('ses+api://default?region=us-east-1', $dsn);
    }

    #[Test]
    public function canBeAddedViaContainerBuilder(): void
    {
        $result = $this->builder->addServiceProvider($this->provider);

        self::assertSame($this->builder, $result);
        self::assertTrue($this->builder->hasDefinition(AmazonMailerConfiguration::class));
        self::assertTrue($this->builder->hasDefinition(SesTransportFactory::class));
        self::assertTrue($this->builder->hasDefinition(NativeTransportFactory::class));
        self::assertTrue($this->builder->hasDefinition(Transport::class));
        self::assertTrue($this->builder->hasDefinition(TransportInterface::class));
        self::assertTrue($this->builder->hasDefinition(Mailer::class));
        self::assertTrue($this->builder->hasDefinition(SesNotificationNormalizer::class));
        self::assertTrue($this->builder->hasDefinition(BounceHandler::class));
        self::assertTrue($this->builder->hasDefinition(ComplaintHandler::class));
    }
}
