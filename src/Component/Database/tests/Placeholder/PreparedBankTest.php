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

        self::assertMatchesRegularExpression('#^[a-f0-9]{12}$#', $bank->idFor('x', ['y']));
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
        $bank->store('aaaaaaaaaaaa', ['x']);
        $bank->store('bbbbbbbbbbbb', ['y']);

        [$cleanSql, $params] = $bank->consume('a = ?/*WPP:aaaaaaaaaaaa*/ AND b = ?/*WPP:bbbbbbbbbbbb*/');

        self::assertSame('a = ? AND b = ?', $cleanSql);
        self::assertSame(['x', 'y'], $params);
    }

    #[Test]
    public function consumeFlattensMultiParamEntries(): void
    {
        $bank = new PreparedBank();
        $bank->store('aaaaaaaaaaaa', ['x', 'y']);
        $bank->store('bbbbbbbbbbbb', ['z']);

        [$cleanSql, $params] = $bank->consume('a = ? AND b = ?/*WPP:aaaaaaaaaaaa*/ OR c = ?/*WPP:bbbbbbbbbbbb*/');

        self::assertSame('a = ? AND b = ? OR c = ?', $cleanSql);
        self::assertSame(['x', 'y', 'z'], $params);
    }

    #[Test]
    public function consumeRemovesEntryFromBank(): void
    {
        $bank = new PreparedBank();
        $bank->store('aaaaaaaaaaaa', ['x']);
        self::assertSame(1, $bank->size());

        $bank->consume('a = ?/*WPP:aaaaaaaaaaaa*/');
        self::assertSame(0, $bank->size());
    }

    #[Test]
    public function consumeReturnsEmptyParamsWhenSqlHasNoMarker(): void
    {
        $bank = new PreparedBank();
        $bank->store('aaaaaaaaaaaa', ['x']);

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
        $bank->store('aaaaaaaaaaaa', ['x']);

        // Unknown markers are stripped along with known ones; no params are added for them.
        [$cleanSql, $params] = $bank->consume('a = ?/*WPP:aaaaaaaaaaaa*/ AND b = ?/*WPP:cafebabecafe*/');

        self::assertSame('a = ? AND b = ?', $cleanSql);
        self::assertSame(['x'], $params);
    }

    #[Test]
    public function resetDropsAllEntries(): void
    {
        $bank = new PreparedBank();
        $bank->store('aaaaaaaaaaaa', ['x']);
        $bank->store('bbbbbbbbbbbb', ['y']);

        $bank->reset();

        self::assertSame(0, $bank->size());
    }
}
