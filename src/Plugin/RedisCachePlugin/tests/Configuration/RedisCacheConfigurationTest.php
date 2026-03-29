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

namespace WpPack\Plugin\RedisCachePlugin\Tests\Configuration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Plugin\RedisCachePlugin\Configuration\RedisCacheConfiguration;

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
}
