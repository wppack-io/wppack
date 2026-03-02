<?php

declare(strict_types=1);

namespace WpPack\Component\Widget\Attribute\Action;

use WpPack\Component\Hook\Attribute\Action;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class DynamicSidebarBeforeAction extends Action
{
    public function __construct(int $priority = 10)
    {
        parent::__construct('dynamic_sidebar_before', $priority);
    }
}
