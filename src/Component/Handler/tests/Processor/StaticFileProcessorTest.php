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

namespace WPPack\Component\Handler\Tests\Processor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Handler\Configuration;
use WPPack\Component\Handler\Processor\StaticFileProcessor;
use WPPack\Component\HttpFoundation\BinaryFileResponse;
use WPPack\Component\HttpFoundation\Request;

#[CoversClass(StaticFileProcessor::class)]
final class StaticFileProcessorTest extends TestCase
{
    private StaticFileProcessor $processor;

    private string $webRoot;

    protected function setUp(): void
    {
        $this->processor = new StaticFileProcessor();
        $this->webRoot = sys_get_temp_dir() . '/handler_static_' . uniqid();
        mkdir($this->webRoot, 0o777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->webRoot . '/*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        @rmdir($this->webRoot);
    }

    private function config(): Configuration
    {
        return new Configuration(['web_root' => $this->webRoot]);
    }

    #[Test]
    public function returnsBinaryFileResponseForExistingStaticFile(): void
    {
        file_put_contents($this->webRoot . '/logo.png', 'fakepngdata');
        $request = Request::create('/logo.png');

        $result = $this->processor->process($request, $this->config());

        self::assertInstanceOf(BinaryFileResponse::class, $result);
        self::assertSame('image/png', $result->headers['Content-Type'] ?? '');
    }

    #[Test]
    public function returnsNullWhenFileDoesNotExist(): void
    {
        $request = Request::create('/not-there.gif');

        self::assertNull($this->processor->process($request, $this->config()));
    }

    #[Test]
    public function skipsPhpFilesEvenWhenTheyExist(): void
    {
        file_put_contents($this->webRoot . '/script.php', '<?php //');
        $request = Request::create('/script.php');

        self::assertNull($this->processor->process($request, $this->config()));
    }

    #[Test]
    public function phpExtensionCheckIsCaseInsensitive(): void
    {
        file_put_contents($this->webRoot . '/weird.PHP', '<?php //');
        $request = Request::create('/weird.PHP');

        self::assertNull($this->processor->process($request, $this->config()));
    }

    #[Test]
    public function preferPhpSelfOverPathInfo(): void
    {
        file_put_contents($this->webRoot . '/actual.css', 'body{}');

        $request = Request::create('/something-else.css');
        $request->server->set('PHP_SELF', '/actual.css');

        $result = $this->processor->process($request, $this->config());

        self::assertInstanceOf(BinaryFileResponse::class, $result);
        self::assertSame('text/css', $result->headers['Content-Type']);
    }
}
