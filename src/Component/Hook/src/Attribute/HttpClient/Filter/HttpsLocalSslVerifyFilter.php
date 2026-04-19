<?php

/*
 * This file is part of the WPPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WPPack\Component\Hook\Attribute\HttpClient\Filter;

use WPPack\Component\Hook\Attribute\Filter;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class HttpsLocalSslVerifyFilter extends Filter
{
    public function __construct(int $priority = 10)
    {
        parent::__construct('https_local_ssl_verify', $priority);
    }
}
