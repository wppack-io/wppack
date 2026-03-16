<?php

declare(strict_types=1);

namespace WpPack\Component\Messenger\Stamp;

final readonly class HandledStamp implements StampInterface
{
    public function __construct(
        public mixed $result,
        public string $handlerName,
    ) {}
}
