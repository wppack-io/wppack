<?php

declare(strict_types=1);

namespace WpPack\Component\Setting;

final class FieldDefinition
{
    /**
     * @param array<string, mixed> $args
     */
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly \Closure $renderCallback,
        public readonly array $args = [],
    ) {}
}
