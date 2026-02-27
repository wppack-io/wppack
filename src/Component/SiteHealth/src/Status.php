<?php

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
