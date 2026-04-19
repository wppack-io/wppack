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

namespace WPPack\Component\HttpFoundation\Exception;

final class UnauthorizedException extends HttpException
{
    public function __construct(string $message = 'Unauthorized.', string $errorCode = 'http_unauthorized', ?\Throwable $previous = null)
    {
        parent::__construct($message, 401, $errorCode, $previous);
    }
}
