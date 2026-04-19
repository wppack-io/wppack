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

namespace WPPack\Component\EventDispatcher;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\StoppableEventInterface;
use WPPack\Component\EventDispatcher\Exception\InvalidArgumentException;

final class EventDispatcher implements EventDispatcherInterface
{
    /**
     * Wrapped listeners indexed by hook name, priority, and sequence.
     *
     * @var array<string, array<int, list<array{original: callable, wrapped: \Closure}>>>
     */
    private array $wrappedListeners = [];

    public function dispatch(object $event): object
    {
        if ($event instanceof StoppableEventInterface && $event->isPropagationStopped()) {
            return $event;
        }

        do_action(get_class($event), $event);

        return $event;
    }

    /**
     * @param string                          $event        FQCN or WordPress hook name
     * @param callable                        $listener     Listener callable
     * @param int                             $priority     Priority (WordPress style: lower = earlier)
     * @param int                             $acceptedArgs Number of arguments for WordPress hooks (default: PHP_INT_MAX = all)
     * @param string|null $eventClass  Event class for WordPress hooks
     */
    public function addListener(
        string $event,
        callable $listener,
        int $priority = 10,
        int $acceptedArgs = \PHP_INT_MAX,
        ?string $eventClass = null,
    ): void {
        if ($eventClass !== null && !is_subclass_of($eventClass, WordPressEvent::class) && $eventClass !== WordPressEvent::class) {
            throw new InvalidArgumentException(sprintf(
                'The eventClass "%s" must be WordPressEvent or a subclass of it.',
                $eventClass,
            ));
        }

        if (class_exists($event) && !is_subclass_of($event, WordPressEvent::class)) {
            // Custom event FQCN → register directly
            add_filter($event, $listener, $priority);
        } else {
            // WordPress hook name or WordPressEvent subclass → wrap
            $isWpEventSubclass = is_subclass_of($event, WordPressEvent::class);
            $hookName = $isWpEventSubclass ? $event::HOOK_NAME : $event;
            $class = $eventClass ?? ($isWpEventSubclass ? $event : WordPressEvent::class);

            $wrapped = static function () use ($hookName, $class, $listener): mixed {
                $event = new $class($hookName, func_get_args());
                $listener($event);

                return $event->filterValue;
            };

            $this->wrappedListeners[$hookName][$priority][] = [
                'original' => $listener,
                'wrapped' => $wrapped,
            ];

            add_filter($hookName, $wrapped, $priority, $acceptedArgs);
        }
    }

    public function removeListener(string $event, callable $listener, int $priority = 10): void
    {
        if (class_exists($event) && !is_subclass_of($event, WordPressEvent::class)) {
            @remove_filter($event, $listener, $priority);
        } else {
            $hookName = is_subclass_of($event, WordPressEvent::class) ? $event::HOOK_NAME : $event;

            foreach ($this->wrappedListeners[$hookName][$priority] ?? [] as $i => $entry) {
                if ($entry['original'] === $listener) {
                    @remove_filter($hookName, $entry['wrapped'], $priority);
                    unset($this->wrappedListeners[$hookName][$priority][$i]);

                    return;
                }
            }
        }
    }

    public function addSubscriber(EventSubscriberInterface $subscriber): void
    {
        foreach ($subscriber::getSubscribedEvents() as $event => $params) {
            if (\is_string($params)) {
                $this->addListener($event, [$subscriber, $params]);
            } elseif (\is_string($params[0] ?? null)) {
                $this->addSubscriberListener($event, $subscriber, $params);
            } else {
                /** @var list<array{string, int}|array{string, int, int}|array{string, int, int, class-string<WordPressEvent>}> $params */
                foreach ($params as $p) {
                    $this->addSubscriberListener($event, $subscriber, $p);
                }
            }
        }
    }

    public function removeSubscriber(EventSubscriberInterface $subscriber): void
    {
        foreach ($subscriber::getSubscribedEvents() as $event => $params) {
            if (\is_string($params)) {
                $this->removeListener($event, [$subscriber, $params]);
            } elseif (\is_string($params[0] ?? null)) {
                $this->removeListener($event, [$subscriber, $params[0]], $params[1] ?? 10);
            } else {
                foreach ($params as $p) {
                    $this->removeListener($event, [$subscriber, $p[0]], $p[1] ?? 10);
                }
            }
        }
    }

    public function hasListeners(string $event): bool
    {
        return has_filter($event) !== false;
    }

    /**
     * @param array<int, mixed> $params
     */
    private function addSubscriberListener(string $event, EventSubscriberInterface $subscriber, array $params): void
    {
        /** @var string $method */
        $method = $params[0];
        /** @var int $priority */
        $priority = $params[1] ?? 10;
        /** @var int $acceptedArgs */
        $acceptedArgs = $params[2] ?? \PHP_INT_MAX;
        /** @var string|null $eventClass */
        $eventClass = $params[3] ?? null;

        $this->addListener($event, [$subscriber, $method], $priority, $acceptedArgs, $eventClass);
    }
}
