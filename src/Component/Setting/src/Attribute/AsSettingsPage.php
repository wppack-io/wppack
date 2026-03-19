<?php

declare(strict_types=1);

namespace WpPack\Component\Setting\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class AsSettingsPage
{
    public function __construct(
        public readonly string $slug,
        public readonly string $title,
        public readonly string $menuTitle = '',
        public readonly string $optionName = '',
        public readonly string $optionGroup = '',
        public readonly ?string $parent = 'options-general.php',
        public readonly ?string $icon = null,
        public readonly ?int $position = null,
    ) {}
}
