<?php

declare(strict_types=1);

namespace WpPack\Component\Hook\Attribute\Ajax\Action;

use WpPack\Component\Hook\Attribute\Action;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class CheckAjaxRefererAction extends Action
{
    public function __construct(int $priority = 10)
    {
        parent::__construct('check_ajax_referer', $priority);
    }
}
