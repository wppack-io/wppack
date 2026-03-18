<?php

declare(strict_types=1);

namespace WpPack\Component\Serializer\Tests\Fixtures;

final readonly class ObjectWithNullable
{
    public function __construct(
        public ?string $name,
        public ?int $value = null,
    ) {}
}
