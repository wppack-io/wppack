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

namespace WpPack\Component\DashboardWidget\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class AsDashboardWidget
{
    public function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly string $context = 'normal',
        public readonly string $priority = 'core',
    ) {}
}
