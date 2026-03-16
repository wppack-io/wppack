<?php

declare(strict_types=1);

namespace WpPack\Component\Messenger\Stamp;

final readonly class PriorityStamp implements StampInterface
{
    public function __construct(
        public int $priority = 0,
    ) {}
}
