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

namespace WPPack\Component\Admin\Tests\Fixtures;

/**
 * Creates and tears down a per-test `js/build/` scratch directory under
 * sys_get_temp_dir(), so settings-page tests can stub the asset build
 * output that AbstractAdminPage looks for.
 *
 * Each of the 7 plugin settings-page test suites used to inline the
 * same setUp / tearDown pair; this trait centralises it.
 *
 * Usage:
 *   use BuildDirFixtureTrait;
 *   protected function setUp(): void    { $this->createBuildDir('wppack-scim'); }
 *   protected function tearDown(): void { $this->cleanupBuildDir(); }
 */
trait BuildDirFixtureTrait
{
    protected string $buildDir;

    protected function createBuildDir(string $prefix): string
    {
        $this->buildDir = sys_get_temp_dir() . '/' . $prefix . '-test-' . uniqid() . '/js/build';
        mkdir($this->buildDir, 0o777, true);

        return $this->buildDir;
    }

    protected function cleanupBuildDir(): void
    {
        $base = \dirname($this->buildDir, 2);
        if (is_dir($base)) {
            array_map('unlink', glob($this->buildDir . '/*') ?: []);
            @rmdir($this->buildDir);
            @rmdir(\dirname($this->buildDir));
            @rmdir($base);
        }
    }
}
