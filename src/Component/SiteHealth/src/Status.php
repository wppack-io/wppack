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

namespace WpPack\Component\SiteHealth;

enum Status: string
{
    case Good = 'good';
    case Recommended = 'recommended';
    case Critical = 'critical';

    public function badgeColor(): string
    {
        return match ($this) {
            self::Good => 'green',
            self::Recommended => 'orange',
            self::Critical => 'red',
        };
    }
}
