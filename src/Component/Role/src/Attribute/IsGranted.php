<?php

declare(strict_types=1);

namespace WpPack\Component\Role\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class IsGranted
{
    public function __construct(
        public readonly string $attribute,
        public readonly mixed $subject = null,
        public readonly string $message = 'Access Denied.',
        public readonly int $statusCode = 403,
    ) {}
}
