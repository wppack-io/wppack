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

namespace WpPack\Component\Serializer\Tests\Encoder;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Serializer\Encoder\JsonEncoder;
use WpPack\Component\Serializer\Exception\NotEncodableValueException;

#[CoversClass(JsonEncoder::class)]
final class JsonEncoderTest extends TestCase
{
    private JsonEncoder $encoder;

    protected function setUp(): void
    {
        $this->encoder = new JsonEncoder();
    }

    #[Test]
    public function encodeArray(): void
    {
        $result = $this->encoder->encode(['key' => 'value', 'number' => 42], 'json');

        self::assertSame('{"key":"value","number":42}', $result);
    }

    #[Test]
    public function decodeJson(): void
    {
        $result = $this->encoder->decode('{"key":"value","number":42}', 'json');

        self::assertSame(['key' => 'value', 'number' => 42], $result);
    }

    #[Test]
    public function encodeHandlesUnicode(): void
    {
        $result = $this->encoder->encode(['text' => '日本語'], 'json');

        self::assertSame('{"text":"日本語"}', $result);
    }

    #[Test]
    public function encodeThrowsOnUnencodableValue(): void
    {
        $this->expectException(NotEncodableValueException::class);

        // INF is not a valid JSON value
        $this->encoder->encode(\INF, 'json');
    }

    #[Test]
    public function encodeThrowsOnNaN(): void
    {
        $this->expectException(NotEncodableValueException::class);

        $this->encoder->encode(\NAN, 'json');
    }

    #[Test]
    public function decodeThrowsOnInvalidJson(): void
    {
        $this->expectException(NotEncodableValueException::class);

        $this->encoder->decode('invalid-json', 'json');
    }

    #[Test]
    public function decodeThrowsOnEmptyString(): void
    {
        $this->expectException(NotEncodableValueException::class);

        $this->encoder->decode('', 'json');
    }

    #[Test]
    public function decodeThrowsOnTruncatedJson(): void
    {
        $this->expectException(NotEncodableValueException::class);

        $this->encoder->decode('{"key":', 'json');
    }

    #[Test]
    public function supportsEncoding(): void
    {
        self::assertTrue($this->encoder->supportsEncoding('json'));
        self::assertFalse($this->encoder->supportsEncoding('xml'));
        self::assertFalse($this->encoder->supportsEncoding('csv'));
        self::assertFalse($this->encoder->supportsEncoding(''));
    }

    #[Test]
    public function supportsDecoding(): void
    {
        self::assertTrue($this->encoder->supportsDecoding('json'));
        self::assertFalse($this->encoder->supportsDecoding('xml'));
        self::assertFalse($this->encoder->supportsDecoding('csv'));
        self::assertFalse($this->encoder->supportsDecoding(''));
    }

    #[Test]
    public function encodeScalarValues(): void
    {
        self::assertSame('"hello"', $this->encoder->encode('hello', 'json'));
        self::assertSame('42', $this->encoder->encode(42, 'json'));
        self::assertSame('true', $this->encoder->encode(true, 'json'));
        self::assertSame('null', $this->encoder->encode(null, 'json'));
    }

    #[Test]
    public function decodeReturnsAssociativeArray(): void
    {
        $result = $this->encoder->decode('{"nested":{"a":1}}', 'json');

        self::assertIsArray($result);
        self::assertIsArray($result['nested']);
        self::assertSame(1, $result['nested']['a']);
    }

    #[Test]
    public function formatConstant(): void
    {
        self::assertSame('json', JsonEncoder::FORMAT);
    }
}
