<?php

declare(strict_types=1);

namespace WpPack\Component\Serializer\Tests\Fixtures;

final readonly class ObjectWithDatetime
{
    public function __construct(
        public string $name,
        public \DateTimeImmutable $createdAt,
    ) {}
}
