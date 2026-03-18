<?php

declare(strict_types=1);

namespace WpPack\Component\Hook\Attribute\HttpClient\Filter;

use WpPack\Component\Hook\Attribute\Filter;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class HttpsLocalSslVerifyFilter extends Filter
{
    public function __construct(int $priority = 10)
    {
        parent::__construct('https_local_ssl_verify', $priority);
    }
}
