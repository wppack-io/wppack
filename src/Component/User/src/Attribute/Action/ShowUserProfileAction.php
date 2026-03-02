<?php

declare(strict_types=1);

namespace WpPack\Component\User\Attribute\Action;

use WpPack\Component\Hook\Attribute\Action;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class ShowUserProfileAction extends Action
{
    public function __construct(int $priority = 10)
    {
        parent::__construct('show_user_profile', $priority);
    }
}
