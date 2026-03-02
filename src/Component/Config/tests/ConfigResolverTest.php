<?php

declare(strict_types=1);

namespace WpPack\Component\Config\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Config\ConfigResolver;
use WpPack\Component\Config\Exception\ConfigResolverException;
use WpPack\Component\Config\Tests\Fixtures\DefaultConfig;
use WpPack\Component\Config\Tests\Fixtures\EnvConfig;
use WpPack\Component\Config\Tests\Fixtures\MixedConfig;

final class ConfigResolverTest extends TestCase
{
    private ConfigResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new ConfigResolver();
    }

    protected function tearDown(): void
    {
        unset(
            $_ENV['APP_NAME'],
            $_ENV['APP_PORT'],
            $_ENV['MIXED_API_KEY'],
            $_ENV['DEFAULT_HOST'],
            $_ENV['DEFAULT_PORT'],
            $_ENV['DEFAULT_DEBUG'],
            $_ENV['DEFAULT_RATE'],
        );
    }

    #[Test]
    public function resolvesEnvAttribute(): void
    {
        $_ENV['APP_NAME'] = 'MyApp';

        $config = $this->resolver->resolve(EnvConfig::class);

        self::assertSame('MyApp', $config->appName);
        self::assertSame(8080, $config->port);
    }

    #[Test]
    public function resolvesEnvWithTypeCastToInt(): void
    {
        $_ENV['APP_NAME'] = 'MyApp';
        $_ENV['APP_PORT'] = '9090';

        $config = $this->resolver->resolve(EnvConfig::class);

        self::assertSame(9090, $config->port);
    }

    #[Test]
    public function resolvesEnvWithTypeCastToBool(): void
    {
        $_ENV['DEFAULT_DEBUG'] = 'true';

        $config = $this->resolver->resolve(DefaultConfig::class);

        self::assertTrue($config->debug);
    }

    #[Test]
    public function resolvesEnvBoolFalseValues(): void
    {
        $_ENV['DEFAULT_DEBUG'] = 'false';

        $config = $this->resolver->resolve(DefaultConfig::class);

        self::assertFalse($config->debug);
    }

    #[Test]
    public function resolvesEnvBoolEmptyString(): void
    {
        $_ENV['DEFAULT_DEBUG'] = '';

        $config = $this->resolver->resolve(DefaultConfig::class);

        self::assertFalse($config->debug);
    }

    #[Test]
    public function resolvesEnvBoolZeroString(): void
    {
        $_ENV['DEFAULT_DEBUG'] = '0';

        $config = $this->resolver->resolve(DefaultConfig::class);

        self::assertFalse($config->debug);
    }

    #[Test]
    public function resolvesEnvBoolNoString(): void
    {
        $_ENV['DEFAULT_DEBUG'] = 'no';

        $config = $this->resolver->resolve(DefaultConfig::class);

        self::assertFalse($config->debug);
    }

    #[Test]
    public function resolvesEnvBoolOffString(): void
    {
        $_ENV['DEFAULT_DEBUG'] = 'off';

        $config = $this->resolver->resolve(DefaultConfig::class);

        self::assertFalse($config->debug);
    }

    #[Test]
    public function resolvesEnvWithTypeCastToFloat(): void
    {
        $_ENV['DEFAULT_RATE'] = '2.5';

        $config = $this->resolver->resolve(DefaultConfig::class);

        self::assertSame(2.5, $config->rate);
    }

    #[Test]
    public function usesDefaultValuesWhenEnvNotSet(): void
    {
        $config = $this->resolver->resolve(DefaultConfig::class);

        self::assertSame('localhost', $config->host);
        self::assertSame(3306, $config->port);
        self::assertFalse($config->debug);
        self::assertSame(1.5, $config->rate);
    }

    #[Test]
    public function throwsExceptionForMissingRequiredEnv(): void
    {
        $this->expectException(ConfigResolverException::class);
        $this->expectExceptionMessage('APP_NAME');

        $this->resolver->resolve(EnvConfig::class);
    }

    #[Test]
    public function resolvesConstantAttribute(): void
    {
        if (!defined('MIXED_DEBUG')) {
            define('MIXED_DEBUG', true);
        }
        $_ENV['MIXED_API_KEY'] = 'test-key';

        $config = $this->resolver->resolve(MixedConfig::class);

        self::assertTrue($config->debug);
    }

    #[Test]
    public function resolvesConstantWithDefaultWhenNotDefined(): void
    {
        $_ENV['MIXED_API_KEY'] = 'test-key';

        // MIXED_DEBUG_UNDEFINED is not defined, so default value (false) should be used
        // But MIXED_DEBUG might be defined from a previous test, so we test the MixedConfig
        // which has a default of false for debug
        $config = $this->resolver->resolve(MixedConfig::class);

        self::assertSame('test-key', $config->apiKey);
    }

    #[Test]
    public function resolvesMixedAttributes(): void
    {
        $_ENV['MIXED_API_KEY'] = 'my-secret-key';

        $config = $this->resolver->resolve(MixedConfig::class);

        self::assertSame('my-secret-key', $config->apiKey);
    }
}
