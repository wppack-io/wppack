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

namespace WpPack\Component\Database\Tests\Placeholder;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Database\Placeholder\PreparedBank;

final class PreparedBankTest extends TestCase
{
    #[Test]
    public function idIsDeterministicForSameInputs(): void
    {
        $bank = new PreparedBank();

        self::assertSame(
            $bank->idFor('a = ?', ['foo']),
            $bank->idFor('a = ?', ['foo']),
        );
    }

    #[Test]
    public function idDiffersWhenValuesDiffer(): void
    {
        $bank = new PreparedBank();

        self::assertNotSame(
            $bank->idFor('a = ?', ['foo']),
            $bank->idFor('a = ?', ['bar']),
        );
    }

    #[Test]
    public function idDiffersWhenSqlDiffers(): void
    {
        $bank = new PreparedBank();

        self::assertNotSame(
            $bank->idFor('a = ?', ['foo']),
            $bank->idFor('b = ?', ['foo']),
        );
    }

    #[Test]
    public function idIsTwelveHexCharacters(): void
    {
        $bank = new PreparedBank();

        self::assertMatchesRegularExpression('#^[a-f0-9]{16}$#', $bank->idFor('x', ['y']));
    }

    #[Test]
    public function markerFormatIsStandardSqlComment(): void
    {
        $bank = new PreparedBank();

        self::assertSame('/*WPP:abc123def456*/', $bank->markerFor('abc123def456'));
    }

    #[Test]
    public function consumeExtractsParamsInAppearanceOrder(): void
    {
        $bank = new PreparedBank();
        $bank->store('aaaaaaaaaaaaaaaa', ['x']);
        $bank->store('bbbbbbbbbbbbbbbb', ['y']);

        [$cleanSql, $params] = $bank->consume('a = ?/*WPP:aaaaaaaaaaaaaaaa*/ AND b = ?/*WPP:bbbbbbbbbbbbbbbb*/');

        self::assertSame('a = ? AND b = ?', $cleanSql);
        self::assertSame(['x', 'y'], $params);
    }

    #[Test]
    public function consumeFlattensMultiParamEntries(): void
    {
        $bank = new PreparedBank();
        $bank->store('aaaaaaaaaaaaaaaa', ['x', 'y']);
        $bank->store('bbbbbbbbbbbbbbbb', ['z']);

        [$cleanSql, $params] = $bank->consume('a = ? AND b = ?/*WPP:aaaaaaaaaaaaaaaa*/ OR c = ?/*WPP:bbbbbbbbbbbbbbbb*/');

        self::assertSame('a = ? AND b = ? OR c = ?', $cleanSql);
        self::assertSame(['x', 'y', 'z'], $params);
    }

    #[Test]
    public function consumeRemovesEntryFromBank(): void
    {
        $bank = new PreparedBank();
        $bank->store('aaaaaaaaaaaaaaaa', ['x']);
        self::assertSame(1, $bank->size());

        $bank->consume('a = ?/*WPP:aaaaaaaaaaaaaaaa*/');
        self::assertSame(0, $bank->size());
    }

    #[Test]
    public function consumeReturnsEmptyParamsWhenSqlHasNoMarker(): void
    {
        $bank = new PreparedBank();
        $bank->store('aaaaaaaaaaaaaaaa', ['x']);

        [$cleanSql, $params] = $bank->consume('SELECT 1');

        self::assertSame('SELECT 1', $cleanSql);
        self::assertSame([], $params);
        // Bank entry preserved because nothing was consumed
        self::assertSame(1, $bank->size());
    }

    #[Test]
    public function consumeSkipsUnknownIdsWithoutFailing(): void
    {
        $bank = new PreparedBank();
        $bank->store('aaaaaaaaaaaaaaaa', ['x']);

        // Unknown markers are stripped along with known ones; no params are added for them.
        [$cleanSql, $params] = $bank->consume('a = ?/*WPP:aaaaaaaaaaaaaaaa*/ AND b = ?/*WPP:cafebabecafebabe*/');

        self::assertSame('a = ? AND b = ?', $cleanSql);
        self::assertSame(['x'], $params);
    }

    #[Test]
    public function resetDropsAllEntries(): void
    {
        $bank = new PreparedBank();
        $bank->store('aaaaaaaaaaaaaaaa', ['x']);
        $bank->store('bbbbbbbbbbbbbbbb', ['y']);

        $bank->reset();

        self::assertSame(0, $bank->size());
    }

    #[Test]
    public function idsAreStableWithinInstanceAndDivergeBetweenInstances(): void
    {
        // Same input → same id within an instance (dedup still works).
        $a = new PreparedBank();
        self::assertSame($a->idFor('x = ?', ['q']), $a->idFor('x = ?', ['q']));

        // Different instances generate different ids because each one carries
        // its own random salt. This makes markers non-forgeable across
        // WpPackWpdb instances: a marker from a previous request / another
        // process can't splice into the current bank.
        $b = new PreparedBank();
        self::assertNotSame($a->idFor('x = ?', ['q']), $b->idFor('x = ?', ['q']));
    }

    #[Test]
    public function explicitSaltProducesReproducibleIds(): void
    {
        // Dependency-injectable salt for callers that need stable ids across
        // instances (tests, deterministic fixtures).
        $a = new PreparedBank('fixed-salt');
        $b = new PreparedBank('fixed-salt');

        self::assertSame(
            $a->idFor('x = ?', ['q']),
            $b->idFor('x = ?', ['q']),
        );
    }
}
