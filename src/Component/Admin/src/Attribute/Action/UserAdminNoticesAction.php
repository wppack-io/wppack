<?php

declare(strict_types=1);

namespace WpPack\Component\Admin\Attribute\Action;

use WpPack\Component\Hook\Attribute\Action;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class UserAdminNoticesAction extends Action
{
    public function __construct(int $priority = 10)
    {
        parent::__construct('user_admin_notices', $priority);
    }
}
