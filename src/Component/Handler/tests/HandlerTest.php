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

namespace WpPack\Component\Handler\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Handler\Configuration;
use WpPack\Component\Handler\Handler;
use WpPack\Component\HttpFoundation\Request;

final class HandlerTest extends TestCase
{
    private string $webRoot;
    private string $testFile;
    private string $wpIndex;

    protected function setUp(): void
    {
        $this->webRoot = sys_get_temp_dir() . '/handler_test_' . uniqid();
        mkdir($this->webRoot, 0o777, true);

        $this->testFile = $this->webRoot . '/test.php';
        file_put_contents($this->testFile, '<?php echo "test";');

        $this->wpIndex = $this->webRoot . '/index.php';
        file_put_contents($this->wpIndex, '<?php // WordPress index');
    }

    protected function tearDown(): void
    {
        foreach (glob($this->webRoot . '/*') as $file) {
            unlink($file);
        }
        if (is_dir($this->webRoot)) {
            rmdir($this->webRoot);
        }
    }

    #[Test]
    public function runReturnsFilePathForPhpFile(): void
    {
        $config = new Configuration([
            'web_root' => $this->webRoot,
            'wordpress_index' => '/index.php',
        ]);
        $handler = new Handler($config);
        $request = Request::create('/test.php');

        $filePath = $handler->run($request);

        self::assertSame($this->testFile, $filePath);
    }

    #[Test]
    public function runReturnsWordPressIndexForUnknownPath(): void
    {
        $config = new Configuration([
            'web_root' => $this->webRoot,
            'wordpress_index' => '/index.php',
        ]);
        $handler = new Handler($config);
        $request = Request::create('/some/page');

        $filePath = $handler->run($request);

        self::assertSame($this->wpIndex, $filePath);
    }

    #[Test]
    public function runReturnsNullForStaticFile(): void
    {
        $staticFile = $this->webRoot . '/style.css';
        file_put_contents($staticFile, 'body {}');

        $config = new Configuration([
            'web_root' => $this->webRoot,
            'wordpress_index' => '/index.php',
        ]);
        $handler = new Handler($config);
        $request = Request::create('/style.css');

        ob_start();
        $filePath = $handler->run($request);
        ob_end_clean();

        self::assertNull($filePath);
    }

    #[Test]
    public function runReturnsNullForBlockedPath(): void
    {
        $config = new Configuration([
            'web_root' => $this->webRoot,
            'wordpress_index' => '/index.php',
        ]);
        $handler = new Handler($config);
        $request = Request::create('/.env');

        ob_start();
        $filePath = $handler->run($request);
        ob_end_clean();

        self::assertNull($filePath);
    }
}
