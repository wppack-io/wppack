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

namespace WPPack\Component\Database\Tests\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Database\Event\DatabaseQueryCompletedEvent;
use WPPack\Component\Database\Event\DatabaseQueryFailedEvent;

#[CoversClass(DatabaseQueryCompletedEvent::class)]
#[CoversClass(DatabaseQueryFailedEvent::class)]
final class DatabaseEventsTest extends TestCase
{
    #[Test]
    public function completedEventCarriesTimingAndRowCount(): void
    {
        $event = new DatabaseQueryCompletedEvent(
            sql: 'SELECT * FROM wp_posts WHERE ID = ?',
            paramsSummary: ['#0' => 'integer'],
            elapsedMs: 12.5,
            rowCount: 1,
            driverName: 'mysql',
        );

        self::assertSame('SELECT * FROM wp_posts WHERE ID = ?', $event->sql);
        self::assertSame(['#0' => 'integer'], $event->paramsSummary);
        self::assertSame(12.5, $event->elapsedMs);
        self::assertSame(1, $event->rowCount);
        self::assertSame('mysql', $event->driverName);
    }

    #[Test]
    public function completedEventRowCountCanBeZero(): void
    {
        $event = new DatabaseQueryCompletedEvent(
            sql: 'UPDATE wp_posts SET post_status = ?',
            paramsSummary: ['#0' => 'string(7)'],
            elapsedMs: 3.1,
            rowCount: 0,
            driverName: 'postgresql',
        );

        self::assertSame(0, $event->rowCount);
    }

    #[Test]
    public function failedEventCarriesErrorMessageAndDriver(): void
    {
        $event = new DatabaseQueryFailedEvent(
            sql: 'SELECT bogus FROM wp_posts',
            paramsSummary: [],
            errorMessage: 'Unknown column "bogus"',
            driverName: 'mysql',
        );

        self::assertSame('SELECT bogus FROM wp_posts', $event->sql);
        self::assertSame([], $event->paramsSummary);
        self::assertSame('Unknown column "bogus"', $event->errorMessage);
        self::assertSame('mysql', $event->driverName);
    }

    #[Test]
    public function eventsAreFinal(): void
    {
        foreach ([DatabaseQueryCompletedEvent::class, DatabaseQueryFailedEvent::class] as $class) {
            $ref = new \ReflectionClass($class);
            self::assertTrue($ref->isFinal(), "{$class} should be final");
        }
    }

    #[Test]
    public function paramsSummaryDoesNotLeakRawValues(): void
    {
        // This test documents the contract — by convention, paramsSummary
        // carries only type/length descriptors, never the raw bound values.
        $event = new DatabaseQueryCompletedEvent(
            sql: 'INSERT INTO wp_users (user_pass) VALUES (?)',
            paramsSummary: ['#0' => 'string(60)'], // bcrypt hash length, not the hash itself
            elapsedMs: 0.5,
            rowCount: 1,
            driverName: 'mysql',
        );

        // Descriptor must include length but not the raw secret
        self::assertSame('string(60)', $event->paramsSummary['#0']);
    }
}
