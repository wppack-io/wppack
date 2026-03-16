<?php

declare(strict_types=1);

namespace WpPack\Component\Messenger\Stamp;

final readonly class DelayStamp implements StampInterface
{
    public function __construct(
        public int $delayInMilliseconds,
    ) {}
}
