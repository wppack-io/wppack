<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Profiler;

use WpPack\Component\Stopwatch\Stopwatch;

final class Profiler
{
    public function __construct(
        private readonly Stopwatch $stopwatch,
    ) {}

    /**
     * @template T
     * @param \Closure(): T $callback
     * @return T
     */
    public function profile(string $name, \Closure $callback, string $category = 'default'): mixed
    {
        $this->stopwatch->start($name, $category);

        try {
            return $callback();
        } finally {
            $this->stopwatch->stop($name);
        }
    }

    public function getStopwatch(): Stopwatch
    {
        return $this->stopwatch;
    }
}
