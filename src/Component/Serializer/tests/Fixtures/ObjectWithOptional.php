<?php

declare(strict_types=1);

namespace WpPack\Component\Serializer\Tests\Fixtures;

final readonly class ObjectWithOptional
{
    public function __construct(
        public string $name,
        public string $description = 'none',
    ) {}
}
