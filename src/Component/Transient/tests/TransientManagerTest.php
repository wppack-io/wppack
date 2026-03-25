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

namespace WpPack\Component\Transient\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Transient\TransientManager;

final class TransientManagerTest extends TestCase
{
    private const TEST_TRANSIENT = 'wppack_test_transient';

    private TransientManager $manager;

    protected function setUp(): void
    {
        $this->manager = new TransientManager();

        delete_transient(self::TEST_TRANSIENT);
    }

    protected function tearDown(): void
    {
        delete_transient(self::TEST_TRANSIENT);
    }

    #[Test]
    public function getReturnsFalseForNonExistentTransient(): void
    {
        self::assertFalse($this->manager->get(self::TEST_TRANSIENT));
    }

    #[Test]
    public function getReturnsValueAfterSet(): void
    {
        $this->manager->set(self::TEST_TRANSIENT, 'test-value');

        self::assertSame('test-value', $this->manager->get(self::TEST_TRANSIENT));
    }

    #[Test]
    public function getReturnsArrayValue(): void
    {
        $array = ['key' => 'value', 'nested' => ['a', 'b']];
        $this->manager->set(self::TEST_TRANSIENT, $array);

        self::assertSame($array, $this->manager->get(self::TEST_TRANSIENT));
    }

    #[Test]
    public function setStoresValue(): void
    {
        self::assertTrue($this->manager->set(self::TEST_TRANSIENT, 'value'));
        self::assertSame('value', get_transient(self::TEST_TRANSIENT));
    }

    #[Test]
    public function deleteRemovesTransient(): void
    {
        $this->manager->set(self::TEST_TRANSIENT, 'value');

        self::assertTrue($this->manager->delete(self::TEST_TRANSIENT));
        self::assertFalse($this->manager->get(self::TEST_TRANSIENT));
    }

    #[Test]
    public function deleteReturnsFalseForNonExistentTransient(): void
    {
        self::assertFalse($this->manager->delete(self::TEST_TRANSIENT));
    }
}
