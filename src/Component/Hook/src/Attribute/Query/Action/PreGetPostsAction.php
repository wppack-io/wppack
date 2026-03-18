<?php

declare(strict_types=1);

namespace WpPack\Component\Hook\Attribute\Query\Action;

use WpPack\Component\Hook\Attribute\Action;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class PreGetPostsAction extends Action
{
    public function __construct(int $priority = 10)
    {
        parent::__construct('pre_get_posts', $priority);
    }
}
