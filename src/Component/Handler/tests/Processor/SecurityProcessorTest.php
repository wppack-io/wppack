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

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Handler\Configuration;
use WPPack\Component\Handler\Exception\SecurityException;
use WPPack\Component\Handler\Processor\SecurityProcessor;
use WPPack\Component\HttpFoundation\Request;

final class SecurityProcessorTest extends TestCase
{
    private SecurityProcessor $processor;

    protected function setUp(): void
    {
        $this->processor = new SecurityProcessor();
    }

    #[Test]
    public function allowsNormalPaths(): void
    {
        $request = Request::create('/index.php');
        $config = new Configuration(['web_root' => sys_get_temp_dir()]);

        $result = $this->processor->process($request, $config);

        self::assertNull($result);
    }

    #[Test]
    public function blocksGitPath(): void
    {
        $request = Request::create('/.git/config');
        $config = new Configuration(['web_root' => sys_get_temp_dir()]);

        $this->expectException(SecurityException::class);
        $this->processor->process($request, $config);
    }

    #[Test]
    public function blocksEnvFile(): void
    {
        $request = Request::create('/.env');
        $config = new Configuration(['web_root' => sys_get_temp_dir()]);

        $this->expectException(SecurityException::class);
        $this->processor->process($request, $config);
    }

    #[Test]
    public function blocksWpConfig(): void
    {
        $request = Request::create('/wp-config.php');
        $config = new Configuration(['web_root' => sys_get_temp_dir()]);

        $this->expectException(SecurityException::class);
        $this->processor->process($request, $config);
    }

    #[Test]
    public function blocksDirectoryTraversal(): void
    {
        $request = Request::create('/../etc/passwd');
        $config = new Configuration(['web_root' => sys_get_temp_dir()]);

        $this->expectException(SecurityException::class);
        $this->processor->process($request, $config);
    }
}
