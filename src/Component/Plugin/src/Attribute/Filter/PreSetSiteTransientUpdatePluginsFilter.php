<?php

declare(strict_types=1);

namespace WpPack\Component\Plugin\Attribute\Filter;

use WpPack\Component\Hook\Attribute\Filter;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class PreSetSiteTransientUpdatePluginsFilter extends Filter
{
    public function __construct(int $priority = 10)
    {
        parent::__construct('pre_set_site_transient_update_plugins', $priority);
    }
}
