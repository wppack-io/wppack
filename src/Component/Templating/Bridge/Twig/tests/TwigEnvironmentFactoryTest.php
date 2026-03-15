<?php

declare(strict_types=1);

namespace WpPack\Component\Templating\Bridge\Twig\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Extension\ExtensionInterface;
use Twig\Loader\FilesystemLoader;
use WpPack\Component\Templating\Bridge\Twig\Extension\WordPressExtension;
use WpPack\Component\Templating\Bridge\Twig\TwigEnvironmentFactory;

final class TwigEnvironmentFactoryTest extends TestCase
{
    #[Test]
    public function createReturnsEnvironment(): void
    {
        $factory = new TwigEnvironmentFactory(
            paths: [__DIR__ . '/Fixtures/templates'],
        );

        $env = $factory->create();

        self::assertInstanceOf(Environment::class, $env);
    }

    #[Test]
    public function pathsAreRegistered(): void
    {
        $path = __DIR__ . '/Fixtures/templates';
        $factory = new TwigEnvironmentFactory(paths: [$path]);

        $env = $factory->create();
        $loader = $env->getLoader();

        self::assertInstanceOf(FilesystemLoader::class, $loader);
        self::assertContains($path, $loader->getPaths());
    }

    #[Test]
    public function optionsAreApplied(): void
    {
        $factory = new TwigEnvironmentFactory(
            paths: [__DIR__ . '/Fixtures/templates'],
            options: ['debug' => true],
        );

        $env = $factory->create();

        self::assertTrue($env->isDebug());
    }

    #[Test]
    public function extensionsAreRegistered(): void
    {
        $extension = new WordPressExtension();
        $factory = new TwigEnvironmentFactory(
            paths: [__DIR__ . '/Fixtures/templates'],
            extensions: [$extension],
        );

        $env = $factory->create();

        self::assertTrue($env->hasExtension(WordPressExtension::class));
    }

    #[Test]
    public function defaultOptionsApplied(): void
    {
        $factory = new TwigEnvironmentFactory(
            paths: [__DIR__ . '/Fixtures/templates'],
        );

        $env = $factory->create();

        // strict_variables defaults to true
        self::assertTrue($env->isStrictVariables());
    }
}
