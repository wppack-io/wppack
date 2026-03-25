<?php

/*
 * This file is part of the WpPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WpPack\Component\HttpFoundation\Exception;

final class NotFoundException extends HttpException
{
    public function __construct(string $message = 'Not found.', string $errorCode = 'http_not_found', ?\Throwable $previous = null)
    {
        parent::__construct($message, 404, $errorCode, $previous);
    }
}
