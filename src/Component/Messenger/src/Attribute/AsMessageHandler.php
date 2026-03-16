<?php

declare(strict_types=1);

namespace WpPack\Component\Messenger\Attribute;

/**
 * Attribute for auto-discovery of message handlers.
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class AsMessageHandler
{
    public function __construct(
        public readonly ?string $bus = null,
        public readonly ?string $fromTransport = null,
        public readonly ?string $handles = null,
        public readonly ?string $method = null,
        public readonly int $priority = 0,
    ) {}
}
