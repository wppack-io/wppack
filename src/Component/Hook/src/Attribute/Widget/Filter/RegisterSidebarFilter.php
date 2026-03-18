<?php

declare(strict_types=1);

namespace WpPack\Component\Hook\Attribute\Widget\Filter;

use WpPack\Component\Hook\Attribute\Filter;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class RegisterSidebarFilter extends Filter
{
    public function __construct(int $priority = 10)
    {
        parent::__construct('register_sidebar', $priority);
    }
}
