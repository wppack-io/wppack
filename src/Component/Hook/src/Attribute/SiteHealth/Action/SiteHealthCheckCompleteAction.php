<?php

declare(strict_types=1);

namespace WpPack\Component\Hook\Attribute\SiteHealth\Action;

use WpPack\Component\Hook\Attribute\Action;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class SiteHealthCheckCompleteAction extends Action
{
    public function __construct(int $priority = 10)
    {
        parent::__construct('site_health_check_complete', $priority);
    }
}
