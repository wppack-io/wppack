<?php

/*
 * This file is part of the WPPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WPPack\Component\SiteHealth\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class AsDebugInfo
{
    public function __construct(
        public readonly string $section,
        public readonly string $label,
        public readonly ?string $description = null,
        public readonly bool $showCount = false,
        public readonly bool $private = false,
    ) {}
}
