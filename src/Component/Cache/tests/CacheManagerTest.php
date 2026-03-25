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

namespace WpPack\Component\Cache\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Cache\CacheManager;

final class CacheManagerTest extends TestCase
{
    private const TEST_KEY = 'wppack_test_cache_key';
    private const TEST_GROUP = 'wppack_test_group';

    private CacheManager $manager;

    protected function setUp(): void
    {
        $this->manager = new CacheManager();

        wp_cache_delete(self::TEST_KEY, '');
        wp_cache_delete(self::TEST_KEY, self::TEST_GROUP);
    }

    protected function tearDown(): void
    {
        wp_cache_delete(self::TEST_KEY, '');
        wp_cache_delete(self::TEST_KEY, self::TEST_GROUP);
    }

    #[Test]
    public function getReturnsFalseForNonExistentKey(): void
    {
        self::assertFalse($this->manager->get(self::TEST_KEY));
    }

    #[Test]
    public function getReturnsValueAfterSet(): void
    {
        $this->manager->set(self::TEST_KEY, 'test-value');

        self::assertSame('test-value', $this->manager->get(self::TEST_KEY));
    }

    #[Test]
    public function getReturnsValueWithGroup(): void
    {
        $this->manager->set(self::TEST_KEY, 'grouped-value', self::TEST_GROUP);

        self::assertSame('grouped-value', $this->manager->get(self::TEST_KEY, self::TEST_GROUP));
        self::assertFalse($this->manager->get(self::TEST_KEY));
    }

    #[Test]
    public function setStoresValue(): void
    {
        self::assertTrue($this->manager->set(self::TEST_KEY, 'value'));
        self::assertSame('value', wp_cache_get(self::TEST_KEY));
    }

    #[Test]
    public function setStoresArrayValue(): void
    {
        $array = ['key' => 'value', 'nested' => ['a', 'b']];
        $this->manager->set(self::TEST_KEY, $array);

        self::assertSame($array, $this->manager->get(self::TEST_KEY));
    }

    #[Test]
    public function addSucceedsForNewKey(): void
    {
        self::assertTrue($this->manager->add(self::TEST_KEY, 'new-value'));
        self::assertSame('new-value', wp_cache_get(self::TEST_KEY));
    }

    #[Test]
    public function addReturnsFalseForExistingKey(): void
    {
        $this->manager->set(self::TEST_KEY, 'existing');

        self::assertFalse($this->manager->add(self::TEST_KEY, 'new'));
        self::assertSame('existing', $this->manager->get(self::TEST_KEY));
    }

    #[Test]
    public function replaceSucceedsForExistingKey(): void
    {
        $this->manager->set(self::TEST_KEY, 'old-value');

        self::assertTrue($this->manager->replace(self::TEST_KEY, 'new-value'));
        self::assertSame('new-value', $this->manager->get(self::TEST_KEY));
    }

    #[Test]
    public function replaceReturnsFalseForNonExistentKey(): void
    {
        self::assertFalse($this->manager->replace(self::TEST_KEY, 'value'));
    }

    #[Test]
    public function deleteRemovesKey(): void
    {
        $this->manager->set(self::TEST_KEY, 'value');

        self::assertTrue($this->manager->delete(self::TEST_KEY));
        self::assertFalse($this->manager->get(self::TEST_KEY));
    }

    #[Test]
    public function flushClearsAllCache(): void
    {
        $this->manager->set(self::TEST_KEY, 'value');
        $this->manager->set('another_key', 'another_value');

        self::assertTrue($this->manager->flush());
        self::assertFalse($this->manager->get(self::TEST_KEY));
        self::assertFalse($this->manager->get('another_key'));
    }

    #[Test]
    public function flushGroupClearsGroup(): void
    {
        if (!$this->manager->supports('flush_group')) {
            self::markTestSkipped('flush_group is not supported by the current object cache backend.');
        }

        $this->manager->set(self::TEST_KEY, 'grouped', self::TEST_GROUP);
        $this->manager->set(self::TEST_KEY, 'default');

        self::assertTrue($this->manager->flushGroup(self::TEST_GROUP));
        self::assertFalse($this->manager->get(self::TEST_KEY, self::TEST_GROUP));
        self::assertSame('default', $this->manager->get(self::TEST_KEY));
    }

    #[Test]
    public function incrementIncreasesValue(): void
    {
        $this->manager->set(self::TEST_KEY, 10);

        $result = $this->manager->increment(self::TEST_KEY);

        self::assertSame(11, $result);
    }

    #[Test]
    public function incrementByOffset(): void
    {
        $this->manager->set(self::TEST_KEY, 10);

        $result = $this->manager->increment(self::TEST_KEY, 5);

        self::assertSame(15, $result);
    }

    #[Test]
    public function decrementDecreasesValue(): void
    {
        $this->manager->set(self::TEST_KEY, 10);

        $result = $this->manager->decrement(self::TEST_KEY);

        self::assertSame(9, $result);
    }

    #[Test]
    public function decrementByOffset(): void
    {
        $this->manager->set(self::TEST_KEY, 10);

        $result = $this->manager->decrement(self::TEST_KEY, 3);

        self::assertSame(7, $result);
    }

    #[Test]
    public function supportsReturnsBool(): void
    {
        $result = $this->manager->supports('flush_group');

        self::assertIsBool($result);
    }
}
