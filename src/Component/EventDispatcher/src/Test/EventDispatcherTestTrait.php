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

namespace WPPack\Component\EventDispatcher\Test;

use WPPack\Component\EventDispatcher\EventDispatcher;

/**
 * Helper trait for testing event listeners.
 */
trait EventDispatcherTestTrait
{
    private ?EventDispatcher $eventDispatcher = null;

    /** @var list<object> */
    private array $dispatchedEvents = [];

    protected function getEventDispatcher(): EventDispatcher
    {
        return $this->eventDispatcher ??= new EventDispatcher();
    }

    protected function dispatch(object $event): object
    {
        $dispatched = $this->getEventDispatcher()->dispatch($event);
        $this->dispatchedEvents[] = $dispatched;

        return $dispatched;
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $eventClass
     *
     * @return T|null
     */
    protected function getLastDispatchedEvent(string $eventClass): ?object
    {
        for ($i = \count($this->dispatchedEvents) - 1; $i >= 0; --$i) {
            if ($this->dispatchedEvents[$i] instanceof $eventClass) {
                return $this->dispatchedEvents[$i];
            }
        }

        return null;
    }

    /**
     * @param class-string $eventClass
     */
    protected function assertEventDispatched(string $eventClass): void
    {
        foreach ($this->dispatchedEvents as $event) {
            if ($event instanceof $eventClass) {
                $this->addToAssertionCount(1);

                return;
            }
        }

        self::fail(sprintf('Event "%s" was not dispatched.', $eventClass));
    }

    /**
     * @param class-string $eventClass
     */
    protected function assertEventNotDispatched(string $eventClass): void
    {
        foreach ($this->dispatchedEvents as $event) {
            if ($event instanceof $eventClass) {
                self::fail(sprintf('Event "%s" was dispatched but should not have been.', $eventClass));
            }
        }

        $this->addToAssertionCount(1);
    }

    protected function resetDispatchedEvents(): void
    {
        $this->dispatchedEvents = [];
    }
}
