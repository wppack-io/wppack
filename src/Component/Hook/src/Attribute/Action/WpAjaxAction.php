<?php

declare(strict_types=1);

namespace WpPack\Component\Hook\Attribute\Action;

use WpPack\Component\Hook\Attribute\Action;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class WpAjaxAction extends Action
{
    public function __construct(
        public readonly string $action,
        int $priority = 10,
    ) {
        parent::__construct("wp_ajax_{$this->action}", $priority);
    }
}
