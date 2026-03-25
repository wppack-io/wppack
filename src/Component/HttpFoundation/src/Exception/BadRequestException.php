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

final class BadRequestException extends HttpException
{
    public function __construct(string $message = 'Bad request.', string $errorCode = 'http_bad_request', ?\Throwable $previous = null)
    {
        parent::__construct($message, 400, $errorCode, $previous);
    }
}
