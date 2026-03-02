<?php

declare(strict_types=1);

namespace WpPack\Component\HttpClient\Attribute\Filter;

use WpPack\Component\Hook\Attribute\Filter;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class HttpRequestArgsFilter extends Filter
{
    public function __construct(int $priority = 10)
    {
        parent::__construct('http_request_args', $priority);
    }
}
