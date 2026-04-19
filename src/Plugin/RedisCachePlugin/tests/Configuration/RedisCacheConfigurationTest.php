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

namespace WPPack\Plugin\RedisCachePlugin\Tests\Configuration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Plugin\RedisCachePlugin\Configuration\RedisCacheConfiguration;

#[CoversClass(RedisCacheConfiguration::class)]
final class RedisCacheConfigurationTest extends TestCase
{
    /** @var list<string> */
    private array $envVars = [
        'WPPACK_CACHE_DSN',
        'WPPACK_CACHE_PREFIX',
        'WPPACK_CACHE_MAX_TTL',
        'WPPACK_CACHE_HASH_ALLOPTIONS',
        'WPPACK_CACHE_ASYNC_FLUSH',
        'WPPACK_CACHE_COMPRESSION',
    ];

    protected function tearDown(): void
    {
        foreach ($this->envVars as $var) {
            putenv($var);
            unset($_ENV[$var]);
        }
    }

    #[Test]
    public function constructorSetsAllProperties(): void
    {
        $config = new RedisCacheConfiguration(
            dsn: 'redis://localhost:6379',
            prefix: 'myapp:',
            maxTtl: 3600,
            hashAlloptions: true,
            asyncFlush: true,
            compression: 'lz4',
        );

        self::assertSame('redis://localhost:6379', $config->dsn);
        self::assertSame('myapp:', $config->prefix);
        self::assertSame(3600, $config->maxTtl);
        self::assertTrue($config->hashAlloptions);
        self::assertTrue($config->asyncFlush);
        self::assertSame('lz4', $config->compression);
    }

    #[Test]
    public function constructorDefaults(): void
    {
        $config = new RedisCacheConfiguration(
            dsn: 'redis://localhost:6379',
        );

        self::assertSame('redis://localhost:6379', $config->dsn);
        self::assertSame('wp:', $config->prefix);
        self::assertNull($config->maxTtl);
        self::assertFalse($config->hashAlloptions);
        self::assertFalse($config->asyncFlush);
        self::assertSame('none', $config->compression);
    }

    #[Test]
    public function fromEnvironmentReadsDsn(): void
    {
        putenv('WPPACK_CACHE_DSN=redis://cache.example.com:6379');

        $config = RedisCacheConfiguration::fromEnvironment();

        self::assertSame('redis://cache.example.com:6379', $config->dsn);
    }

    #[Test]
    public function fromEnvironmentThrowsWhenDsnMissing(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Required environment variable "WPPACK_CACHE_DSN" is not set.');

        RedisCacheConfiguration::fromEnvironment();
    }

    #[Test]
    public function fromEnvironmentReadsEnvSuperglobal(): void
    {
        $_ENV['WPPACK_CACHE_DSN'] = 'redis://env-host:6379';

        $config = RedisCacheConfiguration::fromEnvironment();

        self::assertSame('redis://env-host:6379', $config->dsn);
    }

    #[Test]
    public function fromEnvironmentReadsOptionalPrefix(): void
    {
        putenv('WPPACK_CACHE_DSN=redis://localhost:6379');
        putenv('WPPACK_CACHE_PREFIX=site1:');

        $config = RedisCacheConfiguration::fromEnvironment();

        self::assertSame('site1:', $config->prefix);
    }

    #[Test]
    public function fromEnvironmentReadsOptionalMaxTtl(): void
    {
        putenv('WPPACK_CACHE_DSN=redis://localhost:6379');
        putenv('WPPACK_CACHE_MAX_TTL=7200');

        $config = RedisCacheConfiguration::fromEnvironment();

        self::assertSame(7200, $config->maxTtl);
    }

    #[Test]
    public function fromEnvironmentMaxTtlDefaultsToNull(): void
    {
        putenv('WPPACK_CACHE_DSN=redis://localhost:6379');

        $config = RedisCacheConfiguration::fromEnvironment();

        self::assertNull($config->maxTtl);
    }

    #[Test]
    public function fromEnvironmentReadsOptionalHashAlloptions(): void
    {
        putenv('WPPACK_CACHE_DSN=redis://localhost:6379');
        putenv('WPPACK_CACHE_HASH_ALLOPTIONS=true');

        $config = RedisCacheConfiguration::fromEnvironment();

        self::assertTrue($config->hashAlloptions);
    }

    #[Test]
    public function fromEnvironmentReadsOptionalAsyncFlush(): void
    {
        putenv('WPPACK_CACHE_DSN=redis://localhost:6379');
        putenv('WPPACK_CACHE_ASYNC_FLUSH=1');

        $config = RedisCacheConfiguration::fromEnvironment();

        self::assertTrue($config->asyncFlush);
    }

    #[Test]
    public function fromEnvironmentReadsOptionalCompression(): void
    {
        putenv('WPPACK_CACHE_DSN=redis://localhost:6379');
        putenv('WPPACK_CACHE_COMPRESSION=zstd');

        $config = RedisCacheConfiguration::fromEnvironment();

        self::assertSame('zstd', $config->compression);
    }

    #[Test]
    public function fromEnvironmentDefaultsWithOnlyDsn(): void
    {
        putenv('WPPACK_CACHE_DSN=redis://localhost:6379');

        $config = RedisCacheConfiguration::fromEnvironment();

        self::assertSame('wp:', $config->prefix);
        self::assertNull($config->maxTtl);
        self::assertFalse($config->hashAlloptions);
        self::assertFalse($config->asyncFlush);
        self::assertSame('none', $config->compression);
    }

    #[Test]
    public function getBoolParsesTrueValues(): void
    {
        $trueValues = ['1', 'true', 'yes', 'on'];

        foreach ($trueValues as $value) {
            putenv('WPPACK_CACHE_DSN=redis://localhost:6379');
            putenv('WPPACK_CACHE_HASH_ALLOPTIONS=' . $value);

            $config = RedisCacheConfiguration::fromEnvironment();

            self::assertTrue($config->hashAlloptions, sprintf('Expected true for value "%s"', $value));
        }
    }

    #[Test]
    public function getBoolParsesUppercaseTrueValues(): void
    {
        $trueValues = ['TRUE', 'True', 'YES', 'Yes', 'ON', 'On'];

        foreach ($trueValues as $value) {
            putenv('WPPACK_CACHE_DSN=redis://localhost:6379');
            putenv('WPPACK_CACHE_HASH_ALLOPTIONS=' . $value);

            $config = RedisCacheConfiguration::fromEnvironment();

            self::assertTrue($config->hashAlloptions, sprintf('Expected true for value "%s"', $value));
        }
    }

    #[Test]
    public function getBoolReturnsFalseForNonTrueValues(): void
    {
        $falseValues = ['0', 'false', 'no', 'off', ''];

        foreach ($falseValues as $value) {
            putenv('WPPACK_CACHE_DSN=redis://localhost:6379');
            putenv('WPPACK_CACHE_ASYNC_FLUSH=' . $value);

            $config = RedisCacheConfiguration::fromEnvironment();

            self::assertFalse($config->asyncFlush, sprintf('Expected false for value "%s"', $value));
        }
    }

    #[Test]
    public function getBoolReturnsFalseWhenNotSet(): void
    {
        putenv('WPPACK_CACHE_DSN=redis://localhost:6379');

        $config = RedisCacheConfiguration::fromEnvironment();

        self::assertFalse($config->hashAlloptions);
        self::assertFalse($config->asyncFlush);
    }

    #[Test]
    public function fromEnvironmentOrOptionsReadsEnv(): void
    {
        putenv('WPPACK_CACHE_DSN=redis://env-host:6379');

        $config = RedisCacheConfiguration::fromEnvironmentOrOptions();

        self::assertSame('redis://env-host:6379', $config->dsn);
    }

    #[Test]
    public function fromEnvironmentOrOptionsReadsOption(): void
    {
        update_option(RedisCacheConfiguration::OPTION_NAME, [
            'dsn' => 'redis://option-host:6379',
            'prefix' => 'site1:',
            'maxTtl' => 3600,
            'hashAlloptions' => true,
            'asyncFlush' => false,
            'compression' => 'lz4',
        ]);

        $config = RedisCacheConfiguration::fromEnvironmentOrOptions();

        self::assertSame('redis://option-host:6379', $config->dsn);
        self::assertSame('site1:', $config->prefix);
        self::assertSame(3600, $config->maxTtl);
        self::assertTrue($config->hashAlloptions);
        self::assertFalse($config->asyncFlush);
        self::assertSame('lz4', $config->compression);

        delete_option(RedisCacheConfiguration::OPTION_NAME);
    }

    #[Test]
    public function fromEnvironmentOrOptionsReadsSerializerFromOption(): void
    {
        update_option(RedisCacheConfiguration::OPTION_NAME, [
            'dsn' => 'redis://option-host:6379',
            'serializer' => 'igbinary',
        ]);

        $config = RedisCacheConfiguration::fromEnvironmentOrOptions();

        self::assertSame('redis://option-host:6379', $config->dsn);
        self::assertSame('igbinary', $config->serializer);

        delete_option(RedisCacheConfiguration::OPTION_NAME);
    }

    #[Test]
    public function fromEnvironmentOrOptionsThrowsWhenNothingConfigured(): void
    {
        delete_option(RedisCacheConfiguration::OPTION_NAME);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('WPPACK_CACHE_DSN is not configured.');

        RedisCacheConfiguration::fromEnvironmentOrOptions();
    }

    #[Test]
    public function hasConfigurationReturnsTrueForEnv(): void
    {
        putenv('WPPACK_CACHE_DSN=redis://localhost:6379');

        self::assertTrue(RedisCacheConfiguration::hasConfiguration());
    }

    #[Test]
    public function hasConfigurationReturnsTrueForOption(): void
    {
        update_option(RedisCacheConfiguration::OPTION_NAME, ['dsn' => 'redis://localhost:6379']);

        self::assertTrue(RedisCacheConfiguration::hasConfiguration());

        delete_option(RedisCacheConfiguration::OPTION_NAME);
    }

    #[Test]
    public function hasConfigurationReturnsFalseWhenEmpty(): void
    {
        delete_option(RedisCacheConfiguration::OPTION_NAME);

        self::assertFalse(RedisCacheConfiguration::hasConfiguration());
    }

    #[Test]
    public function optionNameConstant(): void
    {
        self::assertSame('wppack_redis_cache', RedisCacheConfiguration::OPTION_NAME);
    }

    #[Test]
    public function maskedValueConstant(): void
    {
        self::assertSame('********', RedisCacheConfiguration::MASKED_VALUE);
    }

    #[Test]
    public function fromEnvironmentOrOptionsReadsAllGlobalOptionsFromWpOptions(): void
    {
        update_option(RedisCacheConfiguration::OPTION_NAME, [
            'dsn' => 'redis://127.0.0.1:6379',
            'prefix' => 'mysite:',
            'maxTtl' => 3600,
            'hashAlloptions' => true,
            'asyncFlush' => true,
            'compression' => 'zstd',
            'serializer' => 'msgpack',
        ]);

        $config = RedisCacheConfiguration::fromEnvironmentOrOptions();

        self::assertSame('redis://127.0.0.1:6379', $config->dsn);
        self::assertSame('mysite:', $config->prefix);
        self::assertSame(3600, $config->maxTtl);
        self::assertTrue($config->hashAlloptions);
        self::assertTrue($config->asyncFlush);
        self::assertSame('zstd', $config->compression);
        self::assertSame('msgpack', $config->serializer);

        delete_option(RedisCacheConfiguration::OPTION_NAME);
    }

    #[Test]
    public function fromEnvironmentOrOptionsReadsEnvFallback(): void
    {
        $_ENV['WPPACK_CACHE_DSN'] = 'redis://env-host:6379';

        $config = RedisCacheConfiguration::fromEnvironmentOrOptions();

        self::assertSame('redis://env-host:6379', $config->dsn);

        unset($_ENV['WPPACK_CACHE_DSN']);
    }

    #[Test]
    public function hasConfigurationReturnsTrueForEnvSuperglobal(): void
    {
        $_ENV['WPPACK_CACHE_DSN'] = 'redis://env:6379';

        self::assertTrue(RedisCacheConfiguration::hasConfiguration());

        unset($_ENV['WPPACK_CACHE_DSN']);
    }

    #[Test]
    public function fromEnvironmentOrOptionsMaxTtlAsString(): void
    {
        update_option(RedisCacheConfiguration::OPTION_NAME, [
            'dsn' => 'redis://localhost:6379',
            'maxTtl' => '7200',
        ]);

        $config = RedisCacheConfiguration::fromEnvironmentOrOptions();

        self::assertSame(7200, $config->maxTtl);

        delete_option(RedisCacheConfiguration::OPTION_NAME);
    }

    #[Test]
    public function hasConfigurationReturnsFalseForEmptyDsn(): void
    {
        update_option(RedisCacheConfiguration::OPTION_NAME, ['dsn' => '']);

        self::assertFalse(RedisCacheConfiguration::hasConfiguration());

        delete_option(RedisCacheConfiguration::OPTION_NAME);
    }
}
