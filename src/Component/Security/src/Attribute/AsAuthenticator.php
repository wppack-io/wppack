<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class AsAuthenticator
{
    public function __construct(
        public readonly int $priority = 0,
    ) {}
}
