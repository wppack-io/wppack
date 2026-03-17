<?php

declare(strict_types=1);

namespace WpPack\Component\Mime\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\Mime\DependencyInjection\MimeServiceProvider;
use WpPack\Component\Mime\DependencyInjection\RegisterMimeTypeGuessersPass;
use WpPack\Component\Mime\MimeTypeGuesserInterface;
use WpPack\Component\Mime\MimeTypes;
use WpPack\Component\Mime\MimeTypesInterface;

final class MimeServiceProviderTest extends TestCase
{
    protected function tearDown(): void
    {
        MimeTypes::reset();
    }

    #[Test]
    public function registersMimeTypesService(): void
    {
        $builder = new ContainerBuilder();
        $provider = new MimeServiceProvider();
        $provider->register($builder);
        $builder->addCompilerPass(new RegisterMimeTypeGuessersPass());

        $container = $builder->compile();

        self::assertTrue($container->has(MimeTypes::class));
        self::assertInstanceOf(MimeTypes::class, $container->get(MimeTypes::class));
    }

    #[Test]
    public function registersInterfaceAliases(): void
    {
        $builder = new ContainerBuilder();
        $provider = new MimeServiceProvider();
        $provider->register($builder);
        $builder->addCompilerPass(new RegisterMimeTypeGuessersPass());

        $container = $builder->compile();

        self::assertInstanceOf(MimeTypes::class, $container->get(MimeTypesInterface::class));
        self::assertInstanceOf(MimeTypes::class, $container->get(MimeTypeGuesserInterface::class));
    }

    #[Test]
    public function setsDefaultSingleton(): void
    {
        MimeTypes::reset();

        $builder = new ContainerBuilder();
        $provider = new MimeServiceProvider();
        $provider->register($builder);
        $builder->addCompilerPass(new RegisterMimeTypeGuessersPass());

        $container = $builder->compile();

        $service = $container->get(MimeTypes::class);
        \assert($service instanceof MimeTypes);

        self::assertSame($service, MimeTypes::getDefault());
    }

    #[Test]
    public function guessersAreInjectedViaCompilerPass(): void
    {
        $builder = new ContainerBuilder();
        $provider = new MimeServiceProvider();
        $provider->register($builder);
        $builder->addCompilerPass(new RegisterMimeTypeGuessersPass());

        $container = $builder->compile();

        $mimeTypes = $container->get(MimeTypes::class);
        \assert($mimeTypes instanceof MimeTypes);

        self::assertTrue($mimeTypes->isGuesserSupported());
        self::assertSame('image/jpeg', $mimeTypes->getMimeTypes('jpg')[0] ?? null);
    }
}
