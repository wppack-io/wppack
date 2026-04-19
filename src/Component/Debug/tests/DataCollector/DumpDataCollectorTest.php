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

namespace WPPack\Component\Debug\Tests\DataCollector;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Debug\DataCollector\DumpDataCollector;

final class DumpDataCollectorTest extends TestCase
{
    private DumpDataCollector $collector;

    protected function setUp(): void
    {
        $this->collector = new DumpDataCollector();
    }

    #[Test]
    public function getNameReturnsDump(): void
    {
        self::assertSame('dump', $this->collector->getName());
    }

    #[Test]
    public function getLabelReturnsDump(): void
    {
        self::assertSame('Dump', $this->collector->getLabel());
    }

    #[Test]
    public function captureStoresDumpWithFileAndLineInfo(): void
    {
        $this->collector->capture('hello');

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame(1, $data['total_count']);
        self::assertCount(1, $data['dumps']);
        self::assertSame('"hello"', $data['dumps'][0]['data']);
        self::assertArrayHasKey('file', $data['dumps'][0]);
        self::assertIsString($data['dumps'][0]['file']);
        self::assertArrayHasKey('line', $data['dumps'][0]);
        self::assertIsInt($data['dumps'][0]['line']);
        self::assertIsFloat($data['dumps'][0]['timestamp']);
    }

    #[Test]
    public function captureFormatsNull(): void
    {
        $this->collector->capture(null);

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame('null', $data['dumps'][0]['data']);
    }

    #[Test]
    public function captureFormatsBoolTrue(): void
    {
        $this->collector->capture(true);

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame('true', $data['dumps'][0]['data']);
    }

    #[Test]
    public function captureFormatsBoolFalse(): void
    {
        $this->collector->capture(false);

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame('false', $data['dumps'][0]['data']);
    }

    #[Test]
    public function captureFormatsString(): void
    {
        $this->collector->capture('test string');

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame('"test string"', $data['dumps'][0]['data']);
    }

    #[Test]
    public function captureFormatsInteger(): void
    {
        $this->collector->capture(42);

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame('42', $data['dumps'][0]['data']);
    }

    #[Test]
    public function captureFormatsFloat(): void
    {
        $this->collector->capture(3.14);

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame('3.14', $data['dumps'][0]['data']);
    }

    #[Test]
    public function captureFormatsArray(): void
    {
        $this->collector->capture(['a' => 1, 'b' => 2]);

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertStringContainsString('Array', $data['dumps'][0]['data']);
        self::assertStringContainsString('[a] => 1', $data['dumps'][0]['data']);
        self::assertStringContainsString('[b] => 2', $data['dumps'][0]['data']);
    }

    #[Test]
    public function captureFormatsObject(): void
    {
        $obj = new \stdClass();
        $obj->name = 'test';

        $this->collector->capture($obj);

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertStringContainsString('stdClass', $data['dumps'][0]['data']);
        self::assertStringContainsString('name', $data['dumps'][0]['data']);
        self::assertStringContainsString('test', $data['dumps'][0]['data']);
    }

    #[Test]
    public function captureTruncatesLongStrings(): void
    {
        $longString = str_repeat('x', 1000);

        $this->collector->capture($longString);

        $this->collector->collect();
        $data = $this->collector->getData();

        // The output should be truncated: opening quote + 500 chars + ellipsis + closing quote
        self::assertStringStartsWith('"', $data['dumps'][0]['data']);
        self::assertStringContainsString("\u{2026}", $data['dumps'][0]['data']);
        // Total length: 1 (opening quote) + 500 (truncated content) + 1 (ellipsis) + 1 (closing quote) = 503
        self::assertSame(503, mb_strlen($data['dumps'][0]['data']));
        // Verify the original 1000-char string was NOT included fully
        self::assertNotSame(sprintf('"%s"', $longString), $data['dumps'][0]['data']);
    }

    #[Test]
    public function captureTruncatesLongPrintROutput(): void
    {
        // Create a large array that produces print_r output > 10000 chars
        $largeArray = [];
        for ($i = 0; $i < 1000; $i++) {
            $largeArray["key_$i"] = str_repeat('v', 20);
        }

        $this->collector->capture($largeArray);

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertStringEndsWith("... (truncated)", $data['dumps'][0]['data']);
    }

    #[Test]
    public function captureMultipleVariablesInSingleCall(): void
    {
        $this->collector->capture('hello', 42, null);

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame(1, $data['total_count']);
        self::assertSame('"hello"42null', $data['dumps'][0]['data']);
    }

    #[Test]
    public function getIndicatorValueReturnsCount(): void
    {
        $this->collector->capture('a');
        $this->collector->capture('b');
        $this->collector->capture('c');

        $this->collector->collect();

        self::assertSame('3', $this->collector->getIndicatorValue());
    }

    #[Test]
    public function getIndicatorValueReturnsEmptyWhenNoDumps(): void
    {
        $this->collector->collect();

        self::assertSame('', $this->collector->getIndicatorValue());
    }

    #[Test]
    public function getIndicatorColorReturnsYellowWhenDumpsExist(): void
    {
        $this->collector->capture('test');

        $this->collector->collect();

        self::assertSame('yellow', $this->collector->getIndicatorColor());
    }

    #[Test]
    public function getIndicatorColorReturnsDefaultWhenNoDumps(): void
    {
        $this->collector->collect();

        self::assertSame('default', $this->collector->getIndicatorColor());
    }

    #[Test]
    public function resetClearsData(): void
    {
        $this->collector->capture('test');
        $this->collector->collect();

        self::assertNotEmpty($this->collector->getData());

        $this->collector->reset();

        self::assertEmpty($this->collector->getData());

        // After reset, collecting again should yield empty dumps
        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame(0, $data['total_count']);
        self::assertSame([], $data['dumps']);
    }

    #[Test]
    public function multipleCapturesIncrementCount(): void
    {
        $this->collector->capture('first');
        $this->collector->capture('second');
        $this->collector->capture('third');
        $this->collector->capture('fourth');
        $this->collector->capture('fifth');

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertSame(5, $data['total_count']);
        self::assertCount(5, $data['dumps']);
    }

    #[Test]
    public function captureRecordsTimestamp(): void
    {
        $before = microtime(true);
        $this->collector->capture('timed');
        $after = microtime(true);

        $this->collector->collect();
        $data = $this->collector->getData();

        self::assertGreaterThanOrEqual($before, $data['dumps'][0]['timestamp']);
        self::assertLessThanOrEqual($after, $data['dumps'][0]['timestamp']);
    }
}
