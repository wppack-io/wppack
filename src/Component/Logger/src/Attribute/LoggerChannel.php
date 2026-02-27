<?php

declare(strict_types=1);

namespace WpPack\Component\Logger\Attribute;

#[\Attribute(\Attribute::TARGET_PARAMETER)]
final class LoggerChannel
{
    public function __construct(
        public readonly string $channel,
    ) {}
}
