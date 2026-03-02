<?php

declare(strict_types=1);

namespace WpPack\Component\PostType\Attribute\Action;

use WpPack\Component\Hook\Attribute\Action;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class SavePostAction extends Action
{
    public function __construct(
        public readonly ?string $postType = null,
        int $priority = 10,
    ) {
        parent::__construct(
            $this->postType !== null ? "save_post_{$this->postType}" : 'save_post',
            $priority,
        );
    }
}
