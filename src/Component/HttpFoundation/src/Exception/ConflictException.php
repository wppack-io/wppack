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

final class ConflictException extends HttpException
{
    public function __construct(string $message = 'Conflict.', string $errorCode = 'http_conflict', ?\Throwable $previous = null)
    {
        parent::__construct($message, 409, $errorCode, $previous);
    }
}
