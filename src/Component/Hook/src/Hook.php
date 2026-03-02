<?php

declare(strict_types=1);

namespace WpPack\Component\Hook;

abstract class Hook
{
    public function __construct(
        public readonly string $hook,
        public readonly HookType $type,
        public readonly int $priority = 10,
    ) {}
}
