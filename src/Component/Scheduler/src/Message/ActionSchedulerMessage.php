<?php

declare(strict_types=1);

namespace WpPack\Component\Scheduler\Message;

final readonly class ActionSchedulerMessage
{
    /**
     * @param array<mixed> $args
     */
    public function __construct(
        public string $hook,
        public array $args = [],
        public string $group = '',
        public int $actionId = 0,
    ) {}
}
