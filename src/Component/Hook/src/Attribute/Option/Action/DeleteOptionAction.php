<?php

declare(strict_types=1);

namespace WpPack\Component\Hook\Attribute\Option\Action;

use WpPack\Component\Hook\Attribute\Action;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class DeleteOptionAction extends Action
{
    public function __construct(
        public readonly string $name,
        int $priority = 10,
    ) {
        parent::__construct("delete_option_{$this->name}", $priority);
    }
}
