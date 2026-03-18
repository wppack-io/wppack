<?php

declare(strict_types=1);

namespace WpPack\Component\Hook\Attribute\PostType\Action;

use WpPack\Component\Hook\Attribute\Action;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class TransitionPostStatusAction extends Action
{
    public function __construct(int $priority = 10)
    {
        parent::__construct('transition_post_status', $priority);
    }
}
