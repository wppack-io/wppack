<?php

declare(strict_types=1);

namespace WpPack\Component\Ajax\Attribute\Action;

use WpPack\Component\Hook\Attribute\Action;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class WpAjaxNoprivAction extends Action
{
    public function __construct(
        public readonly string $action,
        int $priority = 10,
    ) {
        parent::__construct("wp_ajax_nopriv_{$this->action}", $priority);
    }
}
