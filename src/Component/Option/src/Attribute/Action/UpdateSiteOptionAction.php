<?php

declare(strict_types=1);

namespace WpPack\Component\Option\Attribute\Action;

use WpPack\Component\Hook\Attribute\Action;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class UpdateSiteOptionAction extends Action
{
    public function __construct(
        public readonly string $optionName,
        int $priority = 10,
    ) {
        parent::__construct("update_site_option_{$this->optionName}", $priority);
    }
}
