<?php

declare(strict_types=1);

namespace WpPack\Component\Hook\Attribute\Plugin\Action;

use WpPack\Component\Hook\Attribute\Action;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class PluginLoadedAction extends Action
{
    public function __construct(int $priority = 10)
    {
        parent::__construct('plugin_loaded', $priority);
    }
}
