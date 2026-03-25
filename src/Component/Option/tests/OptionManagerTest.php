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
use WpPack\Component\Option\OptionManager;

final class OptionManagerTest extends TestCase
{
    private const TEST_OPTION = 'wppack_test_option';

    private OptionManager $manager;

    protected function setUp(): void
    {
        $this->manager = new OptionManager();

        delete_option(self::TEST_OPTION);
    }

    protected function tearDown(): void
    {
        delete_option(self::TEST_OPTION);
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
    public function getReturnsSavedValue(): void
    {
        update_option(self::TEST_OPTION, 'test-value');

        self::assertSame('test-value', $this->manager->get(self::TEST_OPTION));
    }

    #[Test]
    public function getReturnsArrayValue(): void
    {
        $array = ['key' => 'value', 'nested' => ['a', 'b']];
        update_option(self::TEST_OPTION, $array);

        self::assertSame($array, $this->manager->get(self::TEST_OPTION));
    }

    #[Test]
    public function addCreatesNewOption(): void
    {
        self::assertTrue($this->manager->add(self::TEST_OPTION, 'new-value'));
        self::assertSame('new-value', get_option(self::TEST_OPTION));
    }

    #[Test]
    public function addReturnsFalseForExistingOption(): void
    {
        $this->manager->add(self::TEST_OPTION, 'first');

        self::assertFalse($this->manager->add(self::TEST_OPTION, 'second'));
        self::assertSame('first', get_option(self::TEST_OPTION));
    }

    #[Test]
    public function addDefaultValueIsEmptyString(): void
    {
        $this->manager->add(self::TEST_OPTION);

        self::assertSame('', get_option(self::TEST_OPTION));
    }

    #[Test]
    public function updateCreatesNewOption(): void
    {
        self::assertTrue($this->manager->update(self::TEST_OPTION, 'new-value'));
        self::assertSame('new-value', get_option(self::TEST_OPTION));
    }

    #[Test]
    public function updateExistingOption(): void
    {
        add_option(self::TEST_OPTION, 'old-value');

        self::assertTrue($this->manager->update(self::TEST_OPTION, 'new-value'));
        self::assertSame('new-value', get_option(self::TEST_OPTION));
    }

    #[Test]
    public function updateReturnsFalseForSameValue(): void
    {
        add_option(self::TEST_OPTION, 'same-value');

        self::assertFalse($this->manager->update(self::TEST_OPTION, 'same-value'));
    }

    #[Test]
    public function updateWithAutoloadParameter(): void
    {
        self::assertTrue($this->manager->update(self::TEST_OPTION, 'value', false));
        self::assertSame('value', get_option(self::TEST_OPTION));
    }

    #[Test]
    public function deleteExistingOption(): void
    {
        add_option(self::TEST_OPTION, 'value');

        self::assertTrue($this->manager->delete(self::TEST_OPTION));
        self::assertFalse(get_option(self::TEST_OPTION));
    }

    #[Test]
    public function deleteReturnsFalseForNonExistentOption(): void
    {
        self::assertFalse($this->manager->delete(self::TEST_OPTION));
    }
}
