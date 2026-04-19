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

namespace WPPack\Component\Debug\Tests\Profiler;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Debug\DataCollector\DataCollectorInterface;
use WPPack\Component\Debug\Exception\InvalidArgumentException;
use WPPack\Component\Debug\Profiler\Profile;

final class ProfileTest extends TestCase
{
    #[Test]
    public function getTokenReturnsConstructorValue(): void
    {
        $profile = new Profile('abc123');

        self::assertSame('abc123', $profile->getToken());
    }

    #[Test]
    public function getTokenDefaultsToEmptyString(): void
    {
        $profile = new Profile();

        self::assertSame('', $profile->getToken());
    }

    #[Test]
    public function addAndGetCollector(): void
    {
        $profile = new Profile();

        $collector = $this->createStub(DataCollectorInterface::class);
        $collector->method('getName')->willReturn('test');

        $profile->addCollector($collector);

        self::assertSame($collector, $profile->getCollector('test'));
    }

    #[Test]
    public function getCollectorThrowsOnMissing(): void
    {
        $profile = new Profile();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Collector "nonexistent" does not exist.');

        $profile->getCollector('nonexistent');
    }

    #[Test]
    public function getCollectorsReturnsAll(): void
    {
        $profile = new Profile();

        $collector1 = $this->createStub(DataCollectorInterface::class);
        $collector1->method('getName')->willReturn('first');

        $collector2 = $this->createStub(DataCollectorInterface::class);
        $collector2->method('getName')->willReturn('second');

        $profile->addCollector($collector1);
        $profile->addCollector($collector2);

        $collectors = $profile->getCollectors();

        self::assertCount(2, $collectors);
        self::assertArrayHasKey('first', $collectors);
        self::assertArrayHasKey('second', $collectors);
    }

    #[Test]
    public function setAndGetUrl(): void
    {
        $profile = new Profile();

        self::assertSame('', $profile->getUrl());

        $profile->setUrl('/example');

        self::assertSame('/example', $profile->getUrl());
    }

    #[Test]
    public function setAndGetMethod(): void
    {
        $profile = new Profile();

        self::assertSame('GET', $profile->getMethod());

        $profile->setMethod('POST');

        self::assertSame('POST', $profile->getMethod());
    }

    #[Test]
    public function setAndGetStatusCode(): void
    {
        $profile = new Profile();

        self::assertSame(200, $profile->getStatusCode());

        $profile->setStatusCode(404);

        self::assertSame(404, $profile->getStatusCode());
    }

    #[Test]
    public function getTimeReturnsNonNegativeFloat(): void
    {
        $profile = new Profile();

        $time = $profile->getTime();

        self::assertIsFloat($time);
        self::assertGreaterThanOrEqual(0.0, $time);
    }
}
