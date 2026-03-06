<?php

declare(strict_types=1);

namespace WpPack\Component\EventDispatcher\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class AsEventListener
{
    public function __construct(
        public readonly ?string $event = null,
        public readonly ?string $method = null,
        public readonly int $priority = 10,
        public readonly int $acceptedArgs = 1,
    ) {}
}
