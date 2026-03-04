<?php

declare(strict_types=1);

namespace WpPack\Component\Translation\Attribute\Action;

use WpPack\Component\Hook\Attribute\Action;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class LoadTextdomainAction extends Action
{
    public function __construct(int $priority = 10)
    {
        parent::__construct('load_textdomain', $priority);
    }
}
