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

namespace WpPack\Plugin\S3StoragePlugin\Tests\Admin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Admin\AbstractAdminPage;
use WpPack\Plugin\S3StoragePlugin\Admin\S3StorageSettingsPage;

#[CoversClass(S3StorageSettingsPage::class)]
final class S3StorageSettingsPageTest extends TestCase
{
    #[Test]
    public function extendsAbstractAdminPage(): void
    {
        $page = new S3StorageSettingsPage();

        self::assertInstanceOf(AbstractAdminPage::class, $page);
    }

    #[Test]
    public function invokeReturnsMountDiv(): void
    {
        $page = new S3StorageSettingsPage();

        $html = $page();

        self::assertStringContainsString('wppack-storage-settings', $html);
        self::assertStringContainsString('<div class="wrap">', $html);
    }
}
