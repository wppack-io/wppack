<?php

declare(strict_types=1);

namespace WpPack\Component\SiteHealth\Attribute;

#[\Attribute(\Attribute::TARGET_METHOD)]
final class SiteHealthNavigationTabsFilter
{
    public function __construct(
        public readonly int $priority = 10,
    ) {}
}
