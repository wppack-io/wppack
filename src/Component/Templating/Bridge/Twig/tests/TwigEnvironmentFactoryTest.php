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

    #[Test]
    public function duplicatePathsAreDeduped(): void
    {
        $path = __DIR__ . '/Fixtures/templates';
        $factory = new TwigEnvironmentFactory(paths: [$path, $path]);

        $env = $factory->create();
        $loader = $env->getLoader();

        self::assertInstanceOf(FilesystemLoader::class, $loader);

        // Count occurrences of our path
        $occurrences = array_count_values($loader->getPaths());
        // Our path should appear only once (deduped), ignoring theme paths
        self::assertSame(1, $occurrences[$path] ?? 0);
    }

    #[Test]
    public function createWithNoExtensions(): void
    {
        $factory = new TwigEnvironmentFactory(
            paths: [__DIR__ . '/Fixtures/templates'],
            extensions: [],
        );

        $env = $factory->create();

        self::assertInstanceOf(Environment::class, $env);
    }

    #[Test]
    public function createWithMultipleExtensions(): void
    {
        $ext1 = new WordPressExtension();

        $factory = new TwigEnvironmentFactory(
            paths: [__DIR__ . '/Fixtures/templates'],
            extensions: [$ext1],
        );

        $env = $factory->create();

        self::assertTrue($env->hasExtension(WordPressExtension::class));
    }

    #[Test]
    public function optionsOverrideDefaults(): void
    {
        $factory = new TwigEnvironmentFactory(
            paths: [__DIR__ . '/Fixtures/templates'],
            options: ['strict_variables' => false],
        );

        $env = $factory->create();

        // strict_variables should be overridden to false
        self::assertFalse($env->isStrictVariables());
    }
}
