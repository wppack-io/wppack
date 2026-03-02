<?php

declare(strict_types=1);

namespace WpPack\Component\Nonce\Attribute\Action;

use WpPack\Component\Hook\Attribute\Action;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class CheckAdminRefererAction extends Action
{
    public function __construct(int $priority = 10)
    {
        parent::__construct('check_admin_referer', $priority);
    }
}
