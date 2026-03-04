<?php

declare(strict_types=1);

namespace WpPack\Component\Rest\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
final class Permission
{
    public function __construct(
        public readonly ?string $capability = null,
        public readonly ?string $callback = null,
        public readonly bool $public = false,
    ) {
        if ($capability !== null && $callback !== null) {
            throw new \LogicException('Permission attribute cannot have both "capability" and "callback".');
        }

        if ($capability === null && $callback === null && !$public) {
            throw new \LogicException('Permission attribute must have "capability", "callback", or "public: true".');
        }
    }
}
