<?php

declare(strict_types=1);

namespace WpPack\Component\Hook\Attribute\Scheduler\Action;

use WpPack\Component\Hook\Attribute\Action;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class ScheduledEventAction extends Action
{
    public function __construct(
        public readonly string $event,
        int $priority = 10,
    ) {
        parent::__construct($this->event, $priority);
    }
}
