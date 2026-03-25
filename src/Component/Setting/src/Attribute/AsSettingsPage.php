<?php

/*
 * This file is part of the WpPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WpPack\Component\Setting\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class AsSettingsPage
{
    public function __construct(
        public readonly string $slug,
        public readonly string $label,
        public readonly string $menuLabel = '',
        public readonly string $optionName = '',
        public readonly string $optionGroup = '',
        public readonly ?string $parent = 'options-general.php',
        public readonly ?string $icon = null,
        public readonly ?int $position = null,
    ) {}
}
