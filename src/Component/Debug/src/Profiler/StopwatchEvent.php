<?php

declare(strict_types=1);

namespace WpPack\Component\Debug\Profiler;

final readonly class StopwatchEvent
{
    public function __construct(
        public string $name,
        public string $category,
        public float $duration,
        public int $memory,
        public float $startTime,
        public float $endTime,
    ) {}
}
