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

namespace WpPack\Component\Option\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Option\SiteOptionManager;

final class SiteOptionManagerTest extends TestCase
{
    private const TEST_OPTION = 'wppack_test_site_option';

    private SiteOptionManager $manager;

    protected function setUp(): void
    {
        $this->manager = new SiteOptionManager();

        delete_site_option(self::TEST_OPTION);
    }

    protected function tearDown(): void
    {
        delete_site_option(self::TEST_OPTION);
    }

    #[Test]
    public function getReturnsFalseForNonExistentOption(): void
    {
        self::assertFalse($this->manager->get(self::TEST_OPTION));
    }

    #[Test]
    public function getReturnsDefaultValueForNonExistentOption(): void
    {
        self::assertSame('default', $this->manager->get(self::TEST_OPTION, 'default'));
    }

    #[Test]
    public function updateCreatesAndUpdatesOption(): void
    {
        self::assertTrue($this->manager->update(self::TEST_OPTION, 'new-value'));
        self::assertSame('new-value', get_site_option(self::TEST_OPTION));

        self::assertTrue($this->manager->update(self::TEST_OPTION, 'updated-value'));
        self::assertSame('updated-value', get_site_option(self::TEST_OPTION));
    }

    #[Test]
    public function deleteExistingOption(): void
    {
        update_site_option(self::TEST_OPTION, 'value');

        self::assertTrue($this->manager->delete(self::TEST_OPTION));
        self::assertFalse(get_site_option(self::TEST_OPTION));
    }

    #[Test]
    public function deleteReturnsFalseForNonExistentOption(): void
    {
        self::assertFalse($this->manager->delete(self::TEST_OPTION));
    }
}
