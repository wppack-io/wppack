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

namespace WpPack\Component\Storage\Tests\Adapter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Storage\Adapter\Dsn;
use WpPack\Component\Storage\Exception\InvalidArgumentException;

#[CoversClass(Dsn::class)]
final class DsnTest extends TestCase
{
    #[Test]
    public function parsesS3VirtualHostedStyleDsn(): void
    {
        $dsn = Dsn::fromString('s3://my-bucket.s3.ap-northeast-1.amazonaws.com/uploads');

        self::assertSame('s3', $dsn->getScheme());
        self::assertSame('my-bucket.s3.ap-northeast-1.amazonaws.com', $dsn->getHost());
        self::assertSame('/uploads', $dsn->getPath());
    }

    #[Test]
    public function parsesS3DsnWithCredentials(): void
    {
        $dsn = Dsn::fromString('s3://AKID:SECRET@my-bucket.s3.us-east-1.amazonaws.com');

        self::assertSame('s3', $dsn->getScheme());
        self::assertSame('AKID', $dsn->getUser());
        self::assertSame('SECRET', $dsn->getPassword());
        self::assertSame('my-bucket.s3.us-east-1.amazonaws.com', $dsn->getHost());
    }

    #[Test]
    public function parsesS3DsnWithoutRegion(): void
    {
        $dsn = Dsn::fromString('s3://my-bucket.s3.amazonaws.com/uploads');

        self::assertSame('my-bucket.s3.amazonaws.com', $dsn->getHost());
        self::assertSame('/uploads', $dsn->getPath());
    }

    #[Test]
    public function parsesPlainBucketHost(): void
    {
        $dsn = Dsn::fromString('s3://my-bucket?region=ap-northeast-1');

        self::assertSame('my-bucket', $dsn->getHost());
        self::assertSame('ap-northeast-1', $dsn->getOption('region'));
    }

    #[Test]
    public function parsesHostWithPort(): void
    {
        $dsn = Dsn::fromString('s3://localhost:9000');

        self::assertSame('localhost', $dsn->getHost());
        self::assertSame(9000, $dsn->getPort());
    }

    #[Test]
    public function parsesHostWithPath(): void
    {
        $dsn = Dsn::fromString('s3://my-bucket.s3.ap-northeast-1.amazonaws.com/wp-content/uploads');

        self::assertSame('s3', $dsn->getScheme());
        self::assertSame('my-bucket.s3.ap-northeast-1.amazonaws.com', $dsn->getHost());
        self::assertSame('/wp-content/uploads', $dsn->getPath());
    }

    #[Test]
    public function parsesQueryOptions(): void
    {
        $dsn = Dsn::fromString('s3://my-bucket?cdn_url=https://cdn.example.com&endpoint=http://localhost:9000');

        $options = $dsn->getOptions();

        self::assertSame('https://cdn.example.com', $options['cdn_url']);
        self::assertSame('http://localhost:9000', $options['endpoint']);
    }

    #[Test]
    public function getOptionReturnsDefault(): void
    {
        $dsn = Dsn::fromString('s3://my-bucket.s3.ap-northeast-1.amazonaws.com');

        self::assertSame('fallback', $dsn->getOption('nonexistent', 'fallback'));
        self::assertNull($dsn->getOption('nonexistent'));
    }

    #[Test]
    public function throwsOnInvalidDsn(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Dsn::fromString('not-a-valid-dsn');
    }

    #[Test]
    public function throwsOnMissingScheme(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Dsn::fromString('localhost:9000');
    }

    #[Test]
    public function throwsOnInvalidFormat(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Dsn::fromString('s3:invalid');
    }

    #[Test]
    public function parsesEncodedCredentials(): void
    {
        $dsn = Dsn::fromString('s3://user%40host:p%40ss@my-bucket.s3.us-east-1.amazonaws.com');

        self::assertSame('user@host', $dsn->getUser());
        self::assertSame('p@ss', $dsn->getPassword());
    }

    #[Test]
    public function parsesUserWithoutPassword(): void
    {
        $dsn = Dsn::fromString('s3://accesskey@my-bucket.s3.us-east-1.amazonaws.com');

        self::assertSame('accesskey', $dsn->getUser());
        self::assertNull($dsn->getPassword());
    }

    #[Test]
    public function parsesMinimalDsn(): void
    {
        $dsn = Dsn::fromString('s3://my-bucket');

        self::assertSame('s3', $dsn->getScheme());
        self::assertSame('my-bucket', $dsn->getHost());
        self::assertNull($dsn->getUser());
        self::assertNull($dsn->getPassword());
        self::assertNull($dsn->getPort());
        self::assertNull($dsn->getPath());
        self::assertSame([], $dsn->getOptions());
    }
}
