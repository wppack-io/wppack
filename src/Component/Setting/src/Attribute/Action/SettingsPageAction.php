<?php

declare(strict_types=1);

namespace WpPack\Component\Setting\Attribute\Action;

use WpPack\Component\Hook\Attribute\Action;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class SettingsPageAction extends Action
{
    public function __construct(
        public readonly string $page,
        int $priority = 10,
    ) {
        parent::__construct("settings_page_{$this->page}", $priority);
    }
}
