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

namespace WPPack\Component\Database\Tests\Translator;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Database\Translator\CachedQueryTranslator;
use WPPack\Component\Database\Translator\QueryTranslatorInterface;

final class CachedQueryTranslatorTest extends TestCase
{
    #[Test]
    public function repeatedTranslateHitsCache(): void
    {
        $inner = new class implements QueryTranslatorInterface {
            public int $calls = 0;
            public function translate(string $sql): array
            {
                ++$this->calls;
                return ['TRANSLATED: ' . $sql];
            }
        };

        $cached = new CachedQueryTranslator($inner);

        $cached->translate('SELECT 1');
        $cached->translate('SELECT 1');
        $cached->translate('SELECT 1');

        self::assertSame(1, $inner->calls, 'Second and third translate() must hit the cache.');
    }

    #[Test]
    public function lruEvictsOldestEntryBeyondCapacity(): void
    {
        $inner = new class implements QueryTranslatorInterface {
            public int $calls = 0;
            public function translate(string $sql): array
            {
                ++$this->calls;
                return [$sql];
            }
        };

        $cached = new CachedQueryTranslator($inner, capacity: 2);

        $cached->translate('A');         // miss → cached
        $cached->translate('B');         // miss → cached, capacity now full
        $cached->translate('A');         // hit, A promoted to most-recent
        $cached->translate('C');         // miss, evicts B (the LRU)
        $cached->translate('B');         // miss again — B was evicted

        self::assertSame(4, $inner->calls);
    }

    #[Test]
    public function failuresAreCachedAndRethrown(): void
    {
        $inner = new class implements QueryTranslatorInterface {
            public int $calls = 0;
            public function translate(string $sql): array
            {
                ++$this->calls;
                throw new \RuntimeException('nope');
            }
        };

        $cached = new CachedQueryTranslator($inner);

        $firstCaught = false;
        try {
            $cached->translate('BAD');
        } catch (\RuntimeException) {
            $firstCaught = true;
        }

        $secondCaught = false;
        try {
            $cached->translate('BAD');
        } catch (\RuntimeException) {
            $secondCaught = true;
        }

        self::assertTrue($firstCaught);
        self::assertTrue($secondCaught);
        self::assertSame(1, $inner->calls, 'A cached throw must not re-enter the underlying translator.');
    }

    #[Test]
    public function clearEvictsEverything(): void
    {
        $inner = new class implements QueryTranslatorInterface {
            public int $calls = 0;
            public function translate(string $sql): array
            {
                ++$this->calls;
                return [$sql];
            }
        };

        $cached = new CachedQueryTranslator($inner);

        $cached->translate('X');
        $cached->translate('X'); // cached
        self::assertSame(1, $inner->calls);

        $cached->clear();
        $cached->translate('X'); // miss after clear

        self::assertSame(2, $inner->calls);
    }

    #[Test]
    public function sizeReportsEntryCount(): void
    {
        $inner = new class implements QueryTranslatorInterface {
            public function translate(string $sql): array
            {
                return [$sql];
            }
        };

        $cached = new CachedQueryTranslator($inner);

        self::assertSame(0, $cached->size());

        $cached->translate('SELECT 1');
        self::assertSame(1, $cached->size());

        $cached->translate('SELECT 2');
        self::assertSame(2, $cached->size());

        // Repeated SQL does not grow the cache
        $cached->translate('SELECT 1');
        self::assertSame(2, $cached->size());

        $cached->clear();
        self::assertSame(0, $cached->size());
    }
}
