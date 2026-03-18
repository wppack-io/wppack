<?php

declare(strict_types=1);

namespace WpPack\Component\Hook\Attribute\Scheduler\Filter;

use WpPack\Component\Hook\Attribute\Filter;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class PreScheduleEventFilter extends Filter
{
    public function __construct(int $priority = 10)
    {
        parent::__construct('pre_schedule_event', $priority);
    }
}
