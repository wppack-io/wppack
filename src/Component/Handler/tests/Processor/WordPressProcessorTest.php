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
use WpPack\Component\Handler\Processor\WordPressProcessor;
use WpPack\Component\HttpFoundation\Request;

final class WordPressProcessorTest extends TestCase
{
    private WordPressProcessor $processor;
    private string $webRoot;
    private string $indexFile;

    protected function setUp(): void
    {
        $this->processor = new WordPressProcessor();
        $this->webRoot = sys_get_temp_dir() . '/handler_wp_test_' . uniqid();
        mkdir($this->webRoot, 0o777, true);
        $this->indexFile = $this->webRoot . '/index.php';
        file_put_contents($this->indexFile, '<?php // WordPress');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->indexFile)) {
            unlink($this->indexFile);
        }
        rmdir($this->webRoot);
    }

    #[Test]
    public function setsWordPressScriptVariables(): void
    {
        $request = Request::create('/some-page');
        $config = new Configuration(['web_root' => $this->webRoot]);

        $result = $this->processor->process($request, $config);

        self::assertInstanceOf(Request::class, $result);
        self::assertSame('/index.php', $result->server->get('SCRIPT_NAME'));
        self::assertSame($this->indexFile, $result->server->get('SCRIPT_FILENAME'));
    }

    #[Test]
    public function skipsWhenScriptFilenameAlreadySet(): void
    {
        $existingFile = $this->webRoot . '/existing.php';
        file_put_contents($existingFile, '<?php');

        $request = Request::create('/existing.php');
        $request->server->set('SCRIPT_FILENAME', $existingFile);
        $config = new Configuration(['web_root' => $this->webRoot]);

        $result = $this->processor->process($request, $config);

        self::assertNull($result);

        unlink($existingFile);
    }

    #[Test]
    public function throwsWhenIndexNotFound(): void
    {
        unlink($this->indexFile);

        $request = Request::create('/page');
        $config = new Configuration(['web_root' => $this->webRoot]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('WordPress index.php not found');
        $this->processor->process($request, $config);
    }
}
