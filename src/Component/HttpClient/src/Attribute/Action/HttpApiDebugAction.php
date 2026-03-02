<?php

declare(strict_types=1);

namespace WpPack\Component\HttpClient\Attribute\Action;

use WpPack\Component\Hook\Attribute\Action;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class HttpApiDebugAction extends Action
{
    public function __construct(int $priority = 10)
    {
        parent::__construct('http_api_debug', $priority);
    }
}
