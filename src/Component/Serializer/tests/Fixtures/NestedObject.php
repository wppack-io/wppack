<?php

declare(strict_types=1);

namespace WpPack\Component\Serializer\Tests\Fixtures;

final readonly class NestedObject
{
    public function __construct(
        public string $label,
        public DummyObject $child,
    ) {}
}
