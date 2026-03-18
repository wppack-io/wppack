<?php

declare(strict_types=1);

namespace WpPack\Component\Serializer\Tests\Fixtures;

final readonly class ObjectWithArrayParam
{
    public function __construct(
        public string $name,
        /** @var list<string> */
        public array $tags = [],
    ) {}
}
