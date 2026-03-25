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

namespace WpPack\Component\HttpClient\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\HttpClient\Uri;

final class UriTest extends TestCase
{
    #[Test]
    public function parseFullUri(): void
    {
        $uri = new Uri('https://user:pass@example.com:8080/path?query=1#fragment');

        self::assertSame('https', $uri->getScheme());
        self::assertSame('user:pass', $uri->getUserInfo());
        self::assertSame('example.com', $uri->getHost());
        self::assertSame(8080, $uri->getPort());
        self::assertSame('/path', $uri->getPath());
        self::assertSame('query=1', $uri->getQuery());
        self::assertSame('fragment', $uri->getFragment());
    }

    #[Test]
    public function emptyUri(): void
    {
        $uri = new Uri();

        self::assertSame('', $uri->getScheme());
        self::assertSame('', $uri->getHost());
        self::assertNull($uri->getPort());
        self::assertSame('', $uri->getPath());
        self::assertSame('', $uri->getQuery());
        self::assertSame('', $uri->getFragment());
        self::assertSame('', (string) $uri);
    }

    #[Test]
    public function defaultPortIsOmitted(): void
    {
        $uri = new Uri('https://example.com:443/path');

        self::assertNull($uri->getPort());
        self::assertSame('https://example.com/path', (string) $uri);
    }

    #[Test]
    public function nonDefaultPortIsKept(): void
    {
        $uri = new Uri('https://example.com:8443/path');

        self::assertSame(8443, $uri->getPort());
        self::assertSame('https://example.com:8443/path', (string) $uri);
    }

    #[Test]
    public function httpDefaultPortIsOmitted(): void
    {
        $uri = new Uri('http://example.com:80/path');

        self::assertNull($uri->getPort());
    }

    #[Test]
    public function getAuthority(): void
    {
        $uri = new Uri('https://user:pass@example.com:8080/path');

        self::assertSame('user:pass@example.com:8080', $uri->getAuthority());
    }

    #[Test]
    public function getAuthorityWithoutUserInfo(): void
    {
        $uri = new Uri('https://example.com/path');

        self::assertSame('example.com', $uri->getAuthority());
    }

    #[Test]
    public function getAuthorityEmptyHost(): void
    {
        $uri = new Uri('/relative/path');

        self::assertSame('', $uri->getAuthority());
    }

    #[Test]
    public function withScheme(): void
    {
        $uri = new Uri('https://example.com');
        $new = $uri->withScheme('http');

        self::assertSame('https', $uri->getScheme());
        self::assertSame('http', $new->getScheme());
    }

    #[Test]
    public function withUserInfo(): void
    {
        $uri = new Uri('https://example.com');
        $new = $uri->withUserInfo('user', 'pass');

        self::assertSame('', $uri->getUserInfo());
        self::assertSame('user:pass', $new->getUserInfo());
    }

    #[Test]
    public function withUserInfoWithoutPassword(): void
    {
        $uri = new Uri('https://example.com');
        $new = $uri->withUserInfo('user');

        self::assertSame('user', $new->getUserInfo());
    }

    #[Test]
    public function withHost(): void
    {
        $uri = new Uri('https://example.com');
        $new = $uri->withHost('other.com');

        self::assertSame('example.com', $uri->getHost());
        self::assertSame('other.com', $new->getHost());
    }

    #[Test]
    public function withPort(): void
    {
        $uri = new Uri('https://example.com');
        $new = $uri->withPort(9090);

        self::assertNull($uri->getPort());
        self::assertSame(9090, $new->getPort());
    }

    #[Test]
    public function withPortNull(): void
    {
        $uri = new Uri('https://example.com:9090');
        $new = $uri->withPort(null);

        self::assertNull($new->getPort());
    }

    #[Test]
    public function withPath(): void
    {
        $uri = new Uri('https://example.com/old');
        $new = $uri->withPath('/new');

        self::assertSame('/old', $uri->getPath());
        self::assertSame('/new', $new->getPath());
    }

    #[Test]
    public function withQuery(): void
    {
        $uri = new Uri('https://example.com?old=1');
        $new = $uri->withQuery('new=2');

        self::assertSame('old=1', $uri->getQuery());
        self::assertSame('new=2', $new->getQuery());
    }

    #[Test]
    public function withFragment(): void
    {
        $uri = new Uri('https://example.com#old');
        $new = $uri->withFragment('new');

        self::assertSame('old', $uri->getFragment());
        self::assertSame('new', $new->getFragment());
    }

    #[Test]
    public function toStringFullUri(): void
    {
        $uri = new Uri('https://user:pass@example.com:8080/path?query=1#frag');

        self::assertSame('https://user:pass@example.com:8080/path?query=1#frag', (string) $uri);
    }

    #[Test]
    public function toStringRelativePath(): void
    {
        $uri = new Uri('/path?query=1');

        self::assertSame('/path?query=1', (string) $uri);
    }

    #[Test]
    public function schemeCaseInsensitive(): void
    {
        $uri = new Uri('HTTPS://example.com');

        self::assertSame('https', $uri->getScheme());
    }

    #[Test]
    public function hostCaseInsensitive(): void
    {
        $uri = new Uri('https://EXAMPLE.COM');

        self::assertSame('example.com', $uri->getHost());
    }

    #[Test]
    public function invalidUriThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Uri('http:///invalid');
    }

    #[Test]
    public function invalidPortThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new Uri('https://example.com'))->withPort(70000);
    }

    #[Test]
    public function negativePortThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new Uri('https://example.com'))->withPort(-1);
    }

    #[Test]
    public function portAbove65535ThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new Uri('https://example.com'))->withPort(70000);
    }

    #[Test]
    public function pathPrefixedWithSlashWhenAuthorityPresent(): void
    {
        $uri = (new Uri())
            ->withScheme('https')
            ->withHost('example.com')
            ->withPath('relative');

        self::assertSame('https://example.com/relative', (string) $uri);
    }
}
