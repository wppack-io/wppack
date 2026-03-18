<?php

declare(strict_types=1);

namespace WpPack\Component\Hook\Attribute\Taxonomy\Action;

use WpPack\Component\Hook\Attribute\Action;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class DeleteTermAction extends Action
{
    public function __construct(
        public readonly string $taxonomy,
        int $priority = 10,
    ) {
        parent::__construct("delete_{$this->taxonomy}", $priority);
    }
}
