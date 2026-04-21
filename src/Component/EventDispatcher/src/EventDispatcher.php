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
     * Inner sequence is `array<int, ...>` (not list) because
     * `removeListener()` uses `unset()` which leaves holes.
     *
     * @var array<string, array<int, array<int, array{original: callable, wrapped: \Closure}>>>
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
     * @param string      $event        FQCN or WordPress hook name
     * @param callable    $listener     Listener callable
     * @param int         $priority     Priority (WordPress style: lower = earlier)
     * @param int         $acceptedArgs Number of arguments for WordPress hooks (default: PHP_INT_MAX = all)
     * @param string|null $eventClass   Event class for WordPress hooks
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
            foreach ($this->normalizeSubscriberParams($params) as $spec) {
                $callback = [$subscriber, $spec['method']];
                if (!\is_callable($callback)) {
                    continue;
                }
                $this->addListener(
                    $event,
                    $callback,
                    $spec['priority'],
                    $spec['acceptedArgs'],
                    $spec['eventClass'],
                );
            }
        }
    }

    public function removeSubscriber(EventSubscriberInterface $subscriber): void
    {
        foreach ($subscriber::getSubscribedEvents() as $event => $params) {
            foreach ($this->normalizeSubscriberParams($params) as $spec) {
                $callback = [$subscriber, $spec['method']];
                if (!\is_callable($callback)) {
                    continue;
                }
                $this->removeListener($event, $callback, $spec['priority']);
            }
        }
    }

    public function hasListeners(string $event): bool
    {
        return has_filter($event) !== false;
    }

    /**
     * Flatten the polymorphic `getSubscribedEvents()` value shape into a
     * uniform list of listener specs.
     *
     * Accepted input forms (per EventSubscriberInterface contract):
     *  - `'methodName'`
     *  - `['methodName', priority]`
     *  - `['methodName', priority, acceptedArgs]`
     *  - `['methodName', priority, acceptedArgs, eventClass]`
     *  - `[['methodName1', priority], ['methodName2', priority], ...]`
     *
     * @param string|array<int, mixed> $params
     *
     * @return list<array{method: string, priority: int, acceptedArgs: int, eventClass: class-string<WordPressEvent>|null}>
     */
    private function normalizeSubscriberParams(string|array $params): array
    {
        if (\is_string($params)) {
            return [$this->makeSpec($params)];
        }

        // Single tuple: first element is the method name string
        if (\is_string($params[0] ?? null)) {
            return [$this->tupleToSpec($params)];
        }

        // List of tuples
        $specs = [];
        foreach ($params as $tuple) {
            if (\is_array($tuple) && \is_string($tuple[0] ?? null)) {
                $specs[] = $this->tupleToSpec($tuple);
            }
        }

        return $specs;
    }

    /**
     * @param array<int, mixed> $tuple
     *
     * @return array{method: string, priority: int, acceptedArgs: int, eventClass: class-string<WordPressEvent>|null}
     */
    private function tupleToSpec(array $tuple): array
    {
        $method = $tuple[0] ?? null;
        $priority = $tuple[1] ?? 10;
        $acceptedArgs = $tuple[2] ?? \PHP_INT_MAX;
        $eventClass = $tuple[3] ?? null;

        return $this->makeSpec(
            \is_string($method) ? $method : '',
            \is_int($priority) ? $priority : 10,
            \is_int($acceptedArgs) ? $acceptedArgs : \PHP_INT_MAX,
            \is_string($eventClass) && is_subclass_of($eventClass, WordPressEvent::class) ? $eventClass : null,
        );
    }

    /**
     * @param class-string<WordPressEvent>|null $eventClass
     *
     * @return array{method: string, priority: int, acceptedArgs: int, eventClass: class-string<WordPressEvent>|null}
     */
    private function makeSpec(
        string $method,
        int $priority = 10,
        int $acceptedArgs = \PHP_INT_MAX,
        ?string $eventClass = null,
    ): array {
        return [
            'method' => $method,
            'priority' => $priority,
            'acceptedArgs' => $acceptedArgs,
            'eventClass' => $eventClass,
        ];
    }
}
