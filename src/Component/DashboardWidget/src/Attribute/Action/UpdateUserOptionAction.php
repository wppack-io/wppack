<?php

declare(strict_types=1);

namespace WpPack\Component\DashboardWidget\Attribute\Action;

use WpPack\Component\Hook\Attribute\Action;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class UpdateUserOptionAction extends Action
{
    public function __construct(
        public readonly string $option,
        int $priority = 10,
    ) {
        parent::__construct('update_user_option', $priority);
    }
}
