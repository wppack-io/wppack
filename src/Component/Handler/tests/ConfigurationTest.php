<?php

declare(strict_types=1);

namespace WpPack\Component\Handler\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Handler\Configuration;

final class ConfigurationTest extends TestCase
{
    #[Test]
    public function defaultConfiguration(): void
    {
        $config = new Configuration();

        self::assertSame('/index.php', $config->get('wordpress_index'));
        self::assertSame('/wp', $config->get('wp_directory'));
        self::assertSame(['index.php', 'index.html', 'index.htm'], $config->get('index_files'));
        self::assertFalse($config->get('security.allow_directory_listing'));
        self::assertTrue($config->get('security.check_symlinks'));
        self::assertFalse($config->get('multisite.enabled'));
    }

    #[Test]
    public function dotNotationAccess(): void
    {
        $config = new Configuration([
            'security' => ['check_symlinks' => false],
        ]);

        self::assertFalse($config->get('security.check_symlinks'));
    }

    #[Test]
    public function defaultValueForMissingKey(): void
    {
        $config = new Configuration();

        self::assertNull($config->get('nonexistent'));
        self::assertSame('fallback', $config->get('nonexistent', 'fallback'));
    }

    #[Test]
    public function simpleLambdaTrue(): void
    {
        $config = new Configuration(['lambda' => true]);

        self::assertTrue($config->get('lambda.enabled'));
        self::assertSame(['/tmp/uploads', '/tmp/cache', '/tmp/sessions'], $config->get('lambda.directories'));
    }

    #[Test]
    public function simpleLambdaFalse(): void
    {
        $config = new Configuration(['lambda' => false]);

        self::assertFalse($config->get('lambda.enabled'));
    }

    #[Test]
    public function simpleMultisiteTrue(): void
    {
        $config = new Configuration(['multisite' => true]);

        self::assertTrue($config->get('multisite.enabled'));
        self::assertSame('#^/[_0-9a-zA-Z-]+(/wp-.*)#', $config->get('multisite.pattern'));
        self::assertSame('/wp$1', $config->get('multisite.replacement'));
    }

    #[Test]
    public function customMultisiteConfig(): void
    {
        $config = new Configuration([
            'multisite' => [
                'enabled' => true,
                'pattern' => '#^/sites/([^/]+)(/wp-.*)#',
                'replacement' => '/wp$2',
            ],
        ]);

        self::assertTrue($config->get('multisite.enabled'));
        self::assertSame('#^/sites/([^/]+)(/wp-.*)#', $config->get('multisite.pattern'));
    }

    #[Test]
    public function allReturnsFullConfig(): void
    {
        $config = new Configuration();
        $all = $config->all();

        self::assertArrayHasKey('web_root', $all);
        self::assertArrayHasKey('wordpress_index', $all);
        self::assertArrayHasKey('security', $all);
        self::assertArrayHasKey('multisite', $all);
        self::assertArrayHasKey('lambda', $all);
    }
}
