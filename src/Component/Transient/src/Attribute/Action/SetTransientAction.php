<?php

declare(strict_types=1);

namespace WpPack\Component\Transient\Attribute\Action;

use WpPack\Component\Hook\Attribute\Action;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class SetTransientAction extends Action
{
    public function __construct(
        public readonly string $name,
        int $priority = 10,
    ) {
        parent::__construct("set_transient_{$this->name}", $priority);
    }
}
