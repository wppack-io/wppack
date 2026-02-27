<?php

declare(strict_types=1);

namespace WpPack\Component\DependencyInjection\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final class AsAlias
{
    public function __construct(
        public readonly string $id,
    ) {}
}
