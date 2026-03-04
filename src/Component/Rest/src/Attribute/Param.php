<?php

declare(strict_types=1);

namespace WpPack\Component\Rest\Attribute;

#[\Attribute(\Attribute::TARGET_PARAMETER)]
final class Param
{
    /**
     * @param list<string|int>|null $enum
     */
    public function __construct(
        public readonly ?string $description = null,
        public readonly ?array $enum = null,
        public readonly ?int $minimum = null,
        public readonly ?int $maximum = null,
        public readonly ?int $minLength = null,
        public readonly ?int $maxLength = null,
        public readonly ?string $pattern = null,
        public readonly ?string $format = null,
        public readonly ?string $items = null,
        public readonly ?string $validate = null,
        public readonly ?string $sanitize = null,
    ) {}
}
