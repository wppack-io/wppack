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

namespace WPPack\Component\DashboardWidget\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class AsDashboardWidget
{
    /**
     * @param 'column3'|'column4'|'normal'|'side' $context
     * @param 'core'|'default'|'high'|'low'       $priority
     */
    public function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly string $context = 'normal',
        public readonly string $priority = 'core',
    ) {}
}
