<?php

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
    public function decodeThrowsOnInvalidJson(): void
    {
        $this->expectException(NotEncodableValueException::class);

        $this->encoder->decode('invalid-json', 'json');
    }

    #[Test]
    public function supportsEncoding(): void
    {
        self::assertTrue($this->encoder->supportsEncoding('json'));
        self::assertFalse($this->encoder->supportsEncoding('xml'));
    }

    #[Test]
    public function supportsDecoding(): void
    {
        self::assertTrue($this->encoder->supportsDecoding('json'));
        self::assertFalse($this->encoder->supportsDecoding('xml'));
    }
}
