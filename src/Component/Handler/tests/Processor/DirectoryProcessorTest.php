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

namespace WpPack\Component\Handler\Tests\Processor;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Handler\Configuration;
use WpPack\Component\Handler\Processor\DirectoryProcessor;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\HttpFoundation\Response;

final class DirectoryProcessorTest extends TestCase
{
    private DirectoryProcessor $processor;
    private string $webRoot;

    protected function setUp(): void
    {
        $this->processor = new DirectoryProcessor();
        $this->webRoot = sys_get_temp_dir() . '/handler_dir_test_' . uniqid();
        mkdir($this->webRoot . '/with-index', 0o777, true);
        file_put_contents($this->webRoot . '/with-index/index.php', '<?php');
        mkdir($this->webRoot . '/empty-dir', 0o777, true);
    }

    protected function tearDown(): void
    {
        unlink($this->webRoot . '/with-index/index.php');
        rmdir($this->webRoot . '/with-index');
        rmdir($this->webRoot . '/empty-dir');
        rmdir($this->webRoot);
    }

    #[Test]
    public function setsPhpSelfToIndexFile(): void
    {
        $request = Request::create('/with-index/');
        $request->server->set('PHP_SELF', '/with-index/');
        $config = new Configuration(['web_root' => $this->webRoot]);

        $this->processor->process($request, $config);

        self::assertSame('/with-index/index.php', $request->server->get('PHP_SELF'));
        self::assertTrue($request->attributes->get('directory_index'));
    }

    #[Test]
    public function skipsNonDirectory(): void
    {
        $request = Request::create('/nonexistent/');
        $request->server->set('PHP_SELF', '/nonexistent/');
        $config = new Configuration(['web_root' => $this->webRoot]);

        $result = $this->processor->process($request, $config);

        self::assertNull($result);
    }

    #[Test]
    public function directoryListingWhenAllowed(): void
    {
        $request = Request::create('/empty-dir/');
        $request->server->set('PHP_SELF', '/empty-dir/');
        $config = new Configuration([
            'web_root' => $this->webRoot,
            'security' => ['allow_directory_listing' => true],
        ]);

        $result = $this->processor->process($request, $config);

        self::assertInstanceOf(Response::class, $result);
        self::assertSame(200, $result->statusCode);
    }
}
