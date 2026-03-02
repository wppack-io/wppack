<?php

declare(strict_types=1);

namespace WpPack\Component\Plugin\Attribute\Filter;

use WpPack\Component\Hook\Attribute\Filter;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class NetworkPluginActionLinksFilter extends Filter
{
    public function __construct(
        public readonly string $plugin,
        int $priority = 10,
    ) {
        parent::__construct("network_admin_plugin_action_links_{$this->plugin}", $priority);
    }
}
