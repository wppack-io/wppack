<?php

declare(strict_types=1);

namespace WpPack\Component\Handler\Tests\Processor;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Handler\Configuration;
use WpPack\Component\Handler\Processor\TrailingSlashProcessor;
use WpPack\Component\HttpFoundation\RedirectResponse;
use WpPack\Component\HttpFoundation\Request;

final class TrailingSlashProcessorTest extends TestCase
{
    private TrailingSlashProcessor $processor;
    private string $webRoot;

    protected function setUp(): void
    {
        $this->processor = new TrailingSlashProcessor();
        $this->webRoot = sys_get_temp_dir() . '/handler_test_' . uniqid();
        mkdir($this->webRoot . '/subdir', 0o777, true);
    }

    protected function tearDown(): void
    {
        rmdir($this->webRoot . '/subdir');
        rmdir($this->webRoot);
    }

    #[Test]
    public function skipsPathWithTrailingSlash(): void
    {
        $request = Request::create('/subdir/');
        $config = new Configuration(['web_root' => $this->webRoot]);

        $result = $this->processor->process($request, $config);

        self::assertNull($result);
    }

    #[Test]
    public function redirectsDirectoryWithoutTrailingSlash(): void
    {
        $request = Request::create('/subdir');
        $config = new Configuration(['web_root' => $this->webRoot]);

        $result = $this->processor->process($request, $config);

        self::assertInstanceOf(RedirectResponse::class, $result);
        self::assertSame('/subdir/', $result->url);
        self::assertSame(307, $result->statusCode);
    }

    #[Test]
    public function skipsNonDirectoryPath(): void
    {
        $request = Request::create('/nonexistent');
        $config = new Configuration(['web_root' => $this->webRoot]);

        $result = $this->processor->process($request, $config);

        self::assertNull($result);
    }

    #[Test]
    public function redirectsEmptyPath(): void
    {
        $request = Request::create('', server: ['REQUEST_URI' => '']);
        $request->server->set('PHP_SELF', '');
        $config = new Configuration(['web_root' => $this->webRoot]);

        $result = $this->processor->process($request, $config);

        self::assertInstanceOf(RedirectResponse::class, $result);
        self::assertSame('/', $result->url);
    }
}
