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

namespace WPPack\Plugin\AmazonMailerPlugin\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Option\OptionManager;
use WPPack\Plugin\AmazonMailerPlugin\SuppressionList;

#[CoversClass(SuppressionList::class)]
final class SuppressionListTest extends TestCase
{
    private const OPTION_KEY = 'wppack_ses_suppression_list';

    protected function setUp(): void
    {
        delete_option(self::OPTION_KEY);
    }

    protected function tearDown(): void
    {
        delete_option(self::OPTION_KEY);
    }

    #[Test]
    public function addsAddressesToSuppressionList(): void
    {
        $list = new SuppressionList(new OptionManager());

        $list->add(['bounce@example.com', 'invalid@example.com']);

        /** @var string $json */
        $json = get_option(self::OPTION_KEY, '[]');
        /** @var list<string> $stored */
        $stored = json_decode($json, true);

        self::assertContains('bounce@example.com', $stored);
        self::assertContains('invalid@example.com', $stored);
    }

    #[Test]
    public function duplicateAddressesAreNotAdded(): void
    {
        update_option(self::OPTION_KEY, json_encode(['existing@example.com']));

        $list = new SuppressionList(new OptionManager());

        $list->add(['existing@example.com', 'new@example.com']);

        /** @var string $json */
        $json = get_option(self::OPTION_KEY, '[]');
        /** @var list<string> $stored */
        $stored = json_decode($json, true);

        self::assertCount(2, $stored);
        self::assertContains('existing@example.com', $stored);
        self::assertContains('new@example.com', $stored);
    }

    #[Test]
    public function addressesAreNormalizedToLowerCase(): void
    {
        $list = new SuppressionList(new OptionManager());

        $list->add(['User@Example.COM']);

        /** @var string $json */
        $json = get_option(self::OPTION_KEY, '[]');
        /** @var list<string> $stored */
        $stored = json_decode($json, true);

        self::assertSame(['user@example.com'], $stored);
    }

    #[Test]
    public function doesNotUpdateOptionWhenNoNewAddresses(): void
    {
        update_option(self::OPTION_KEY, json_encode(['existing@example.com']));

        $list = new SuppressionList(new OptionManager());

        $list->add(['existing@example.com']);

        /** @var string $json */
        $json = get_option(self::OPTION_KEY, '[]');
        /** @var list<string> $stored */
        $stored = json_decode($json, true);

        self::assertSame(['existing@example.com'], $stored);
    }

    #[Test]
    public function multipleAddressesAllAdded(): void
    {
        $list = new SuppressionList(new OptionManager());

        $list->add(['user1@example.com', 'user2@example.com', 'user3@example.com']);

        /** @var string $json */
        $json = get_option(self::OPTION_KEY, '[]');
        /** @var list<string> $stored */
        $stored = json_decode($json, true);

        self::assertCount(3, $stored);
        self::assertContains('user1@example.com', $stored);
        self::assertContains('user2@example.com', $stored);
        self::assertContains('user3@example.com', $stored);
    }

    #[Test]
    public function emptyArrayDoesNotCreateOption(): void
    {
        $list = new SuppressionList(new OptionManager());

        $list->add([]);

        /** @var string $json */
        $json = get_option(self::OPTION_KEY, '[]');
        /** @var list<string> $stored */
        $stored = json_decode($json, true);

        self::assertSame([], $stored);
    }
}
