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
    public function defaultGuessersAreAvailableWhenBuiltViaDi(): void
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

    #[Test]
    public function defaultGuessersAreNotTagged(): void
    {
        $builder = new ContainerBuilder();
        $provider = new MimeServiceProvider();
        $provider->register($builder);

        $tagged = $builder->findTaggedServiceIds('mime.mime_type_guesser');

        self::assertSame([], $tagged);
    }

    #[Test]
    public function registerMimeTypeGuessersPassSkipsWhenNoMimeTypesDefinition(): void
    {
        $builder = new ContainerBuilder();
        // Do NOT register MimeServiceProvider — MimeTypes definition won't exist

        $pass = new RegisterMimeTypeGuessersPass();
        $pass->process($builder);

        // Should just return without error
        self::assertFalse($builder->hasDefinition(MimeTypes::class));
    }

    #[Test]
    public function registerMimeTypeGuessersPassRegistersTaggedGuessers(): void
    {
        $builder = new ContainerBuilder();
        $provider = new MimeServiceProvider();
        $provider->register($builder);

        // Register a tagged guesser
        $builder->register('custom_guesser', MimeTypeGuesserInterface::class)
            ->addTag('mime.mime_type_guesser');

        $pass = new RegisterMimeTypeGuessersPass();
        $pass->process($builder);

        $definition = $builder->findDefinition(MimeTypes::class);
        $calls = $definition->getMethodCalls();

        $registerCalls = array_filter($calls, static fn(array $call): bool => $call['method'] === 'registerGuesser');
        self::assertNotEmpty($registerCalls);
    }

    #[Test]
    public function registerMimeTypeGuessersPassWithCustomTag(): void
    {
        $builder = new ContainerBuilder();
        $provider = new MimeServiceProvider();
        $provider->register($builder);

        // Register with custom tag
        $builder->register('custom_guesser', MimeTypeGuesserInterface::class)
            ->addTag('custom.guesser_tag');

        $pass = new RegisterMimeTypeGuessersPass('custom.guesser_tag');
        $pass->process($builder);

        $definition = $builder->findDefinition(MimeTypes::class);
        $calls = $definition->getMethodCalls();

        $registerCalls = array_filter($calls, static fn(array $call): bool => $call['method'] === 'registerGuesser');
        self::assertNotEmpty($registerCalls);
    }
}
