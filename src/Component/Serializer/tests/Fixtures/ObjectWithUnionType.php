<?php

declare(strict_types=1);

namespace WpPack\Component\Serializer\Tests\Fixtures;

final readonly class ObjectWithUnionType
{
    public function __construct(
        public string $label,
        public DummyObject|null $child = null,
    ) {}
}
