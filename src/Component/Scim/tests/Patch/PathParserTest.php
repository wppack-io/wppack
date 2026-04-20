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

namespace WPPack\Component\Scim\Tests\Patch;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Scim\Exception\InvalidPatchException;
use WPPack\Component\Scim\Patch\PathParser;

#[CoversClass(PathParser::class)]
final class PathParserTest extends TestCase
{
    #[Test]
    public function parsesSingleSegment(): void
    {
        self::assertSame(['userName'], PathParser::parse('userName'));
    }

    #[Test]
    public function parsesNestedPath(): void
    {
        self::assertSame(['name', 'givenName'], PathParser::parse('name.givenName'));
    }

    #[Test]
    public function stripsValueFilterOnMultiValuedAttribute(): void
    {
        // emails[type eq "work"].value → ['emails', 'value']
        self::assertSame(
            ['emails', 'value'],
            PathParser::parse('emails[type eq "work"].value'),
        );
    }

    #[Test]
    public function collapsesConsecutiveDots(): void
    {
        // Two consecutive dots after filter strip produce an empty segment
        // that should be filtered out.
        self::assertSame(['emails', 'value'], PathParser::parse('emails[type eq "work"]..value'));
    }

    #[Test]
    public function emptyPathThrows(): void
    {
        $this->expectException(InvalidPatchException::class);
        PathParser::parse('');
    }

    #[Test]
    public function pathThatCollapsesToNothingThrows(): void
    {
        $this->expectException(InvalidPatchException::class);
        PathParser::parse('[filter-only]');
    }

    #[Test]
    public function setsValueAtRootPath(): void
    {
        $result = PathParser::setValueAtPath([], ['userName'], 'alice');

        self::assertSame(['userName' => 'alice'], $result);
    }

    #[Test]
    public function setsValueAtNestedPath(): void
    {
        $result = PathParser::setValueAtPath([], ['name', 'givenName'], 'Alice');

        self::assertSame(['name' => ['givenName' => 'Alice']], $result);
    }

    #[Test]
    public function setsValuePreservingSiblings(): void
    {
        $data = ['name' => ['givenName' => 'Alice', 'familyName' => 'Wonder']];

        $result = PathParser::setValueAtPath($data, ['name', 'givenName'], 'Alicia');

        self::assertSame(
            ['name' => ['givenName' => 'Alicia', 'familyName' => 'Wonder']],
            $result,
        );
    }

    #[Test]
    public function setValueOverwritesNonArrayIntermediate(): void
    {
        // name is currently a string — the existing scalar is replaced with
        // a nested array instead of corrupting the structure.
        $data = ['name' => 'Alice'];

        $result = PathParser::setValueAtPath($data, ['name', 'givenName'], 'Alice');

        self::assertSame(['name' => ['givenName' => 'Alice']], $result);
    }

    #[Test]
    public function setValueWithEmptySegmentsIsNoop(): void
    {
        self::assertSame(['x' => 1], PathParser::setValueAtPath(['x' => 1], [], 'ignored'));
    }

    #[Test]
    public function removesValueAtRootPath(): void
    {
        $result = PathParser::removeValueAtPath(['userName' => 'alice', 'nickName' => 'a'], ['userName']);

        self::assertSame(['nickName' => 'a'], $result);
    }

    #[Test]
    public function removesValueAtNestedPath(): void
    {
        $data = ['name' => ['givenName' => 'Alice', 'familyName' => 'Wonder']];

        $result = PathParser::removeValueAtPath($data, ['name', 'givenName']);

        self::assertSame(['name' => ['familyName' => 'Wonder']], $result);
    }

    #[Test]
    public function removeValueOnMissingPathIsNoop(): void
    {
        self::assertSame(
            ['x' => 1],
            PathParser::removeValueAtPath(['x' => 1], ['nonexistent']),
        );
    }

    #[Test]
    public function removeValueStopsAtScalarIntermediate(): void
    {
        // name is a string — can't recurse into it, so the remove silently
        // bails instead of corrupting the structure.
        $data = ['name' => 'Alice'];

        $result = PathParser::removeValueAtPath($data, ['name', 'givenName']);

        self::assertSame($data, $result);
    }

    #[Test]
    public function removeValueWithEmptySegmentsIsNoop(): void
    {
        self::assertSame(
            ['x' => 1],
            PathParser::removeValueAtPath(['x' => 1], []),
        );
    }
}
