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

final class ForbiddenException extends HttpException
{
    public function __construct(string $message = 'Forbidden.', string $errorCode = 'http_forbidden', ?\Throwable $previous = null)
    {
        parent::__construct($message, 403, $errorCode, $previous);
    }
}
