<?php

declare(strict_types=1);

namespace WpPack\Component\Mailer\Attribute\Action;

use WpPack\Component\Hook\Attribute\Action;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class WpMailFailedAction extends Action
{
    public function __construct(int $priority = 10)
    {
        parent::__construct('wp_mail_failed', $priority);
    }
}
