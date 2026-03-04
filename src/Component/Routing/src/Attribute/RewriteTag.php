<?php

declare(strict_types=1);

namespace WpPack\Component\Routing\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class RewriteTag
{
    public function __construct(
        public readonly string $tag,
        public readonly string $regex,
    ) {}
}
