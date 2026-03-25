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

namespace WpPack\Component\Filesystem\Tests\WordPress;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Filesystem\WordPress\UploadPath;

final class UploadPathTest extends TestCase
{
    #[Test]
    public function getBasePathReturnsString(): void
    {
        $uploadPath = new UploadPath();

        self::assertIsString($uploadPath->getBasePath());
        self::assertNotEmpty($uploadPath->getBasePath());
    }

    #[Test]
    public function getBaseUrlReturnsString(): void
    {
        $uploadPath = new UploadPath();

        self::assertIsString($uploadPath->getBaseUrl());
        self::assertNotEmpty($uploadPath->getBaseUrl());
    }

    #[Test]
    public function getCurrentPathReturnsString(): void
    {
        $uploadPath = new UploadPath();

        self::assertIsString($uploadPath->getCurrentPath());
        self::assertNotEmpty($uploadPath->getCurrentPath());
    }

    #[Test]
    public function getCurrentUrlReturnsString(): void
    {
        $uploadPath = new UploadPath();

        self::assertIsString($uploadPath->getCurrentUrl());
        self::assertNotEmpty($uploadPath->getCurrentUrl());
    }

    #[Test]
    public function subdirCreatesDirectory(): void
    {
        $uploadPath = new UploadPath();
        $basePath = $uploadPath->getBasePath();
        $subdirPath = $uploadPath->subdir('wppack-test-' . uniqid());

        self::assertStringStartsWith($basePath . '/', $subdirPath);
        self::assertDirectoryExists($subdirPath);

        // Clean up
        if (is_dir($subdirPath)) {
            rmdir($subdirPath);
        }
    }
}
