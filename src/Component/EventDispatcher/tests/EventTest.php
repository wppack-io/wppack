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

namespace WPPack\Component\EventDispatcher\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\StoppableEventInterface;
use WPPack\Component\EventDispatcher\Event;

final class EventTest extends TestCase
{
    #[Test]
    public function implementsStoppableEventInterface(): void
    {
        $event = new Event();

        self::assertInstanceOf(StoppableEventInterface::class, $event);
    }

    #[Test]
    public function propagationIsNotStoppedByDefault(): void
    {
        $event = new Event();

        self::assertFalse($event->isPropagationStopped());
    }

    #[Test]
    public function stopPropagation(): void
    {
        $event = new Event();
        $event->stopPropagation();

        self::assertTrue($event->isPropagationStopped());
    }

    #[Test]
    public function isExtensible(): void
    {
        $event = new class extends Event {};

        self::assertInstanceOf(Event::class, $event);
        self::assertFalse($event->isPropagationStopped());
    }
}
