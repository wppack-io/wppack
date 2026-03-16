<?php

declare(strict_types=1);

namespace WpPack\Component\Scheduler;

interface ScheduleProviderInterface
{
    public function getSchedule(): Schedule;
}
