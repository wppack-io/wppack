<?php

declare(strict_types=1);

namespace WpPack\Component\Scheduler\Bridge\EventBridge;

interface ScheduleGroupResolverInterface
{
    public function resolve(): string;
}
