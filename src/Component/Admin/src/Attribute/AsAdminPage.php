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

namespace WpPack\Component\Admin\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class AsAdminPage
{
    public function __construct(
        public readonly string $slug,
        public readonly string $label,
        public readonly string $menuLabel = '',
        public readonly ?string $parent = null,
        public readonly ?string $icon = null,
        public readonly ?int $position = null,
        public readonly AdminScope $scope = AdminScope::Site,
    ) {}
}
