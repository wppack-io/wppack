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

namespace WPPack\Component\Scim\Tests\Serialization;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Scim\Schema\ScimConstants;
use WPPack\Component\Scim\Serialization\ListResponseSerializer;

#[CoversClass(ListResponseSerializer::class)]
final class ListResponseSerializerTest extends TestCase
{
    #[Test]
    public function wrapsResourcesInListResponseSchema(): void
    {
        $resources = [
            ['id' => '1', 'userName' => 'alice'],
            ['id' => '2', 'userName' => 'bob'],
        ];

        $result = ListResponseSerializer::serialize($resources, totalResults: 2);

        self::assertSame([ScimConstants::LIST_RESPONSE_SCHEMA], $result['schemas']);
        self::assertSame(2, $result['totalResults']);
        self::assertSame(1, $result['startIndex']);
        self::assertSame(2, $result['itemsPerPage']);
        self::assertSame($resources, $result['Resources']);
    }

    #[Test]
    public function itemsPerPageDefaultsToResourceCount(): void
    {
        $result = ListResponseSerializer::serialize([['id' => '1']], totalResults: 50);

        self::assertSame(1, $result['itemsPerPage']);
        self::assertSame(50, $result['totalResults']);
    }

    #[Test]
    public function explicitItemsPerPageOverridesCount(): void
    {
        $result = ListResponseSerializer::serialize(
            [['id' => '1'], ['id' => '2']],
            totalResults: 100,
            startIndex: 21,
            itemsPerPage: 10,
        );

        self::assertSame(21, $result['startIndex']);
        self::assertSame(10, $result['itemsPerPage']);
        self::assertSame(100, $result['totalResults']);
    }

    #[Test]
    public function emptyResultSet(): void
    {
        $result = ListResponseSerializer::serialize([], totalResults: 0);

        self::assertSame(0, $result['totalResults']);
        self::assertSame(0, $result['itemsPerPage']);
        self::assertSame([], $result['Resources']);
    }
}
