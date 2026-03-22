<?php

declare(strict_types=1);

namespace WpPack\Component\Rest\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Rest\RestUrlGenerator;

#[CoversClass(RestUrlGenerator::class)]
final class RestUrlGeneratorTest extends TestCase
{
    private RestUrlGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new RestUrlGenerator();
    }

    #[Test]
    public function urlReturnsRestUrlWithPath(): void
    {
        $url = $this->generator->url('wppack/v1/test');

        self::assertStringContainsString('wppack/v1/test', $url);
    }

    #[Test]
    public function urlReturnsRestUrlWithoutPath(): void
    {
        $url = $this->generator->url();

        self::assertNotEmpty($url);
        self::assertSame(rest_url(), $url);
    }

    #[Test]
    public function prefixReturnsRestUrlPrefix(): void
    {
        $prefix = $this->generator->prefix();

        self::assertSame('wp-json', $prefix);
    }
}
