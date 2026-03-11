<?php

declare(strict_types=1);

namespace WpPack\Component\Stopwatch;

final class Stopwatch
{
    /** @var array<string, array{start: float, memory: int, category: string}> */
    private array $started = [];

    /** @var array<string, StopwatchEvent> */
    private array $events = [];

    public function start(string $name, string $category = 'default'): void
    {
        $this->started[$name] = [
            'start' => hrtime(true) / 1e6,
            'memory' => memory_get_usage(true),
            'category' => $category,
        ];
    }

    public function stop(string $name): StopwatchEvent
    {
        if (!isset($this->started[$name])) {
            throw new \LogicException(sprintf('Event "%s" is not started.', $name));
        }

        $endTime = hrtime(true) / 1e6;
        $started = $this->started[$name];
        $duration = $endTime - $started['start'];
        $memory = memory_get_usage(true);

        $event = new StopwatchEvent(
            name: $name,
            category: $started['category'],
            duration: $duration,
            memory: $memory,
            startTime: $started['start'],
            endTime: $endTime,
        );

        $this->events[$name] = $event;
        unset($this->started[$name]);

        return $event;
    }

    public function isStarted(string $name): bool
    {
        return isset($this->started[$name]);
    }

    public function getEvent(string $name): StopwatchEvent
    {
        if (!isset($this->events[$name])) {
            throw new \LogicException(sprintf('Event "%s" is not available. Did you forget to stop it?', $name));
        }

        return $this->events[$name];
    }

    /**
     * @return array<string, StopwatchEvent>
     */
    public function getEvents(): array
    {
        return $this->events;
    }

    public function reset(): void
    {
        $this->started = [];
        $this->events = [];
    }
}
