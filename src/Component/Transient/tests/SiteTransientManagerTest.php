<?php

declare(strict_types=1);

namespace WpPack\Component\Transient\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Transient\SiteTransientManager;

final class SiteTransientManagerTest extends TestCase
{
    private const TEST_TRANSIENT = 'wppack_test_site_transient';

    private SiteTransientManager $manager;

    protected function setUp(): void
    {
        if (!function_exists('get_site_transient')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

        $this->manager = new SiteTransientManager();

        delete_site_transient(self::TEST_TRANSIENT);
    }

    protected function tearDown(): void
    {
        if (function_exists('delete_site_transient')) {
            delete_site_transient(self::TEST_TRANSIENT);
        }
    }

    #[Test]
    public function getReturnsFalseForNonExistentTransient(): void
    {
        self::assertFalse($this->manager->get(self::TEST_TRANSIENT));
    }

    #[Test]
    public function setStoresAndRetrievesValue(): void
    {
        self::assertTrue($this->manager->set(self::TEST_TRANSIENT, 'new-value'));
        self::assertSame('new-value', get_site_transient(self::TEST_TRANSIENT));
    }

    #[Test]
    public function setUpdatesExistingValue(): void
    {
        $this->manager->set(self::TEST_TRANSIENT, 'first-value');

        self::assertTrue($this->manager->set(self::TEST_TRANSIENT, 'updated-value'));
        self::assertSame('updated-value', get_site_transient(self::TEST_TRANSIENT));
    }

    #[Test]
    public function deleteRemovesTransient(): void
    {
        $this->manager->set(self::TEST_TRANSIENT, 'value');

        self::assertTrue($this->manager->delete(self::TEST_TRANSIENT));
        self::assertFalse(get_site_transient(self::TEST_TRANSIENT));
    }

    #[Test]
    public function deleteReturnsFalseForNonExistentTransient(): void
    {
        self::assertFalse($this->manager->delete(self::TEST_TRANSIENT));
    }
}
