<?php

declare(strict_types=1);

namespace WpPack\Component\Mailer\Attribute\Filter;

use WpPack\Component\Hook\Attribute\Filter;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class WpMailFromFilter extends Filter
{
    public function __construct(int $priority = 10)
    {
        parent::__construct('wp_mail_from', $priority);
    }
}
