<?php

declare(strict_types=1);

namespace WpPack\Component\Scheduler\Message;

final readonly class WpCronMessage
{
    /**
     * @param array<mixed> $args
     */
    public function __construct(
        public string $hook,
        public array $args = [],
        public string|false $schedule = false,
        public int $timestamp = 0,
    ) {}
}
