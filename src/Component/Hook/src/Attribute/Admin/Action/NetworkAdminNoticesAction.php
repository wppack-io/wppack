<?php

declare(strict_types=1);

namespace WpPack\Component\Hook\Attribute\Admin\Action;

use WpPack\Component\Hook\Attribute\Action;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class NetworkAdminNoticesAction extends Action
{
    public function __construct(int $priority = 10)
    {
        parent::__construct('network_admin_notices', $priority);
    }
}
