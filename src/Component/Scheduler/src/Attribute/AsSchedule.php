<?php

declare(strict_types=1);

namespace WpPack\Component\Scheduler\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class AsSchedule
{
    public function __construct(
        public readonly string $name = 'default',
    ) {}
}
