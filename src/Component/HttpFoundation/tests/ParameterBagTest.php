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

namespace WpPack\Component\HttpFoundation\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\HttpFoundation\ParameterBag;

final class ParameterBagTest extends TestCase
{
    #[Test]
    public function allReturnsStoredParameters(): void
    {
        $params = ['foo' => 'bar', 'baz' => 123];
        $bag = new ParameterBag($params);

        self::assertSame($params, $bag->all());
    }

    #[Test]
    public function allReturnsEmptyArrayByDefault(): void
    {
        $bag = new ParameterBag();

        self::assertSame([], $bag->all());
    }

    #[Test]
    public function getReturnsValueForExistingKey(): void
    {
        $bag = new ParameterBag(['name' => 'value']);

        self::assertSame('value', $bag->get('name'));
    }

    #[Test]
    public function getReturnsNullForMissingKey(): void
    {
        $bag = new ParameterBag();

        self::assertNull($bag->get('missing'));
    }

    #[Test]
    public function getReturnsDefaultForMissingKey(): void
    {
        $bag = new ParameterBag();

        self::assertSame('default', $bag->get('missing', 'default'));
    }

    #[Test]
    public function hasReturnsTrueForExistingKey(): void
    {
        $bag = new ParameterBag(['key' => 'value']);

        self::assertTrue($bag->has('key'));
    }

    #[Test]
    public function hasReturnsFalseForMissingKey(): void
    {
        $bag = new ParameterBag();

        self::assertFalse($bag->has('missing'));
    }

    #[Test]
    public function keysReturnsListOfKeys(): void
    {
        $bag = new ParameterBag(['a' => 1, 'b' => 2, 'c' => 3]);

        self::assertSame(['a', 'b', 'c'], $bag->keys());
    }

    #[Test]
    public function countReturnsParameterCount(): void
    {
        $bag = new ParameterBag(['a' => 1, 'b' => 2]);

        self::assertSame(2, $bag->count());
    }

    #[Test]
    public function countReturnsZeroForEmptyBag(): void
    {
        $bag = new ParameterBag();

        self::assertSame(0, $bag->count());
    }

    #[Test]
    public function getStringCastsToString(): void
    {
        $bag = new ParameterBag(['num' => 42, 'float' => 3.14]);

        self::assertSame('42', $bag->getString('num'));
        self::assertSame('3.14', $bag->getString('float'));
    }

    #[Test]
    public function getStringReturnsDefaultForMissingKey(): void
    {
        $bag = new ParameterBag();

        self::assertSame('', $bag->getString('missing'));
        self::assertSame('fallback', $bag->getString('missing', 'fallback'));
    }

    #[Test]
    public function getIntCastsToInt(): void
    {
        $bag = new ParameterBag(['str' => '42', 'float' => 3.7]);

        self::assertSame(42, $bag->getInt('str'));
        self::assertSame(3, $bag->getInt('float'));
    }

    #[Test]
    public function getIntReturnsDefaultForMissingKey(): void
    {
        $bag = new ParameterBag();

        self::assertSame(0, $bag->getInt('missing'));
        self::assertSame(99, $bag->getInt('missing', 99));
    }

    #[Test]
    public function getBooleanReturnsTrueForTruthyValues(): void
    {
        $truthyValues = ['1', 'true', 'on', 'yes'];

        foreach ($truthyValues as $value) {
            $bag = new ParameterBag(['key' => $value]);

            self::assertTrue($bag->getBoolean('key'), "Expected true for value: {$value}");
        }
    }

    #[Test]
    public function getBooleanReturnsFalseForFalsyValues(): void
    {
        $falsyValues = ['0', 'false', 'off', 'no'];

        foreach ($falsyValues as $value) {
            $bag = new ParameterBag(['key' => $value]);

            self::assertFalse($bag->getBoolean('key'), "Expected false for value: {$value}");
        }
    }

    #[Test]
    public function getBooleanReturnsDefaultForMissingKey(): void
    {
        $bag = new ParameterBag();

        self::assertFalse($bag->getBoolean('missing'));
        self::assertTrue($bag->getBoolean('missing', true));
    }

    #[Test]
    public function getAlphaStripsNonAlphaCharacters(): void
    {
        $bag = new ParameterBag(['key' => 'abc123!@#def']);

        self::assertSame('abcdef', $bag->getAlpha('key'));
    }

    #[Test]
    public function getAlphaReturnsDefaultForMissingKey(): void
    {
        $bag = new ParameterBag();

        self::assertSame('', $bag->getAlpha('missing'));
        self::assertSame('fallback', $bag->getAlpha('missing', 'fallback'));
    }

    #[Test]
    public function getAlnumStripsNonAlphanumericCharacters(): void
    {
        $bag = new ParameterBag(['key' => 'abc123!@#def456']);

        self::assertSame('abc123def456', $bag->getAlnum('key'));
    }

    #[Test]
    public function getAlnumReturnsDefaultForMissingKey(): void
    {
        $bag = new ParameterBag();

        self::assertSame('', $bag->getAlnum('missing'));
        self::assertSame('fallback', $bag->getAlnum('missing', 'fallback'));
    }

    #[Test]
    public function setSetsAndOverwritesValue(): void
    {
        $bag = new ParameterBag(['key' => 'original']);

        $bag->set('key', 'updated');
        self::assertSame('updated', $bag->get('key'));

        $bag->set('new_key', 'new_value');
        self::assertSame('new_value', $bag->get('new_key'));
    }

    #[Test]
    public function removeDeletesValue(): void
    {
        $bag = new ParameterBag(['key' => 'value', 'other' => 'keep']);

        $bag->remove('key');
        self::assertFalse($bag->has('key'));
        self::assertTrue($bag->has('other'));
    }
}
