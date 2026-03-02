<?php

declare(strict_types=1);

namespace WpPack\Component\Rest\Attribute\Filter;

use WpPack\Component\Hook\Attribute\Filter;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class RestRequestAfterCallbacksFilter extends Filter
{
    public function __construct(int $priority = 10)
    {
        parent::__construct('rest_request_after_callbacks', $priority);
    }
}
