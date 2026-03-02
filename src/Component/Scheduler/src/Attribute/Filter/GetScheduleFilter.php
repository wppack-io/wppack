<?php

declare(strict_types=1);

namespace WpPack\Component\Scheduler\Attribute\Filter;

use WpPack\Component\Hook\Attribute\Filter;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class GetScheduleFilter extends Filter
{
    public function __construct(int $priority = 10)
    {
        parent::__construct('get_schedule', $priority);
    }
}
