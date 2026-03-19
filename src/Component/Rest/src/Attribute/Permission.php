<?php

declare(strict_types=1);

namespace WpPack\Component\Rest\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
final class Permission
{
    public function __construct(
        public readonly ?string $callback = null,
        public readonly bool $public = false,
    ) {
        if ($callback === null && !$public) {
            throw new \LogicException('Permission attribute must have "callback" or "public: true".');
        }
    }
}
