<?php

declare(strict_types=1);

namespace WpPack\Component\Plugin\Attribute\Action;

use WpPack\Component\Hook\Attribute\Action;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class AfterPluginRowAction extends Action
{
    public function __construct(int $priority = 10)
    {
        parent::__construct('after_plugin_row', $priority);
    }
}
