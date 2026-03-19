<?php

declare(strict_types=1);

namespace WpPack\Component\Admin\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class AsAdminPage
{
    public function __construct(
        public readonly string $slug,
        public readonly string $title,
        public readonly string $menuTitle = '',
        public readonly ?string $parent = null,
        public readonly ?string $icon = null,
        public readonly ?int $position = null,
    ) {}
}
