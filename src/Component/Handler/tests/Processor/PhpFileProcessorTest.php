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
use WpPack\Component\Handler\Processor\PhpFileProcessor;
use WpPack\Component\HttpFoundation\Request;

final class PhpFileProcessorTest extends TestCase
{
    private PhpFileProcessor $processor;
    private string $webRoot;
    private string $testFile;

    protected function setUp(): void
    {
        $this->processor = new PhpFileProcessor();
        $this->webRoot = sys_get_temp_dir() . '/handler_php_test_' . uniqid();
        mkdir($this->webRoot, 0o777, true);
        $this->testFile = $this->webRoot . '/test.php';
        file_put_contents($this->testFile, '<?php echo "test";');
    }

    protected function tearDown(): void
    {
        unlink($this->testFile);
        rmdir($this->webRoot);
    }

    #[Test]
    public function setsScriptVariablesForPhpFile(): void
    {
        $request = Request::create('/test.php');
        $request->server->set('PHP_SELF', '/test.php');
        $config = new Configuration(['web_root' => $this->webRoot]);

        $result = $this->processor->process($request, $config);

        self::assertInstanceOf(Request::class, $result);
        self::assertSame('/test.php', $result->server->get('SCRIPT_NAME'));
        self::assertSame($this->testFile, $result->server->get('SCRIPT_FILENAME'));
    }

    #[Test]
    public function skipsNonPhpFile(): void
    {
        $request = Request::create('/test.html');
        $request->server->set('PHP_SELF', '/test.html');
        $config = new Configuration(['web_root' => $this->webRoot]);

        $result = $this->processor->process($request, $config);

        self::assertNull($result);
    }

    #[Test]
    public function skipsNonExistentFile(): void
    {
        $request = Request::create('/missing.php');
        $request->server->set('PHP_SELF', '/missing.php');
        $config = new Configuration(['web_root' => $this->webRoot]);

        $result = $this->processor->process($request, $config);

        self::assertNull($result);
    }
}
