<?php

declare(strict_types=1);

namespace WpPack\Component\Comment\Attribute\Action;

use WpPack\Component\Hook\Attribute\Action;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class TransitionCommentStatusAction extends Action
{
    public function __construct(int $priority = 10)
    {
        parent::__construct('transition_comment_status', $priority);
    }
}
