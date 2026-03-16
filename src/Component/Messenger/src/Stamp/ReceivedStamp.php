<?php

declare(strict_types=1);

namespace WpPack\Component\Messenger\Stamp;

final readonly class ReceivedStamp implements StampInterface
{
    public function __construct(
        public string $transportName,
    ) {}
}
