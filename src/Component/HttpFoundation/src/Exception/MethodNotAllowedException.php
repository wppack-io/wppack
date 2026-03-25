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

final class MethodNotAllowedException extends HttpException
{
    public function __construct(string $message = 'Method not allowed.', string $errorCode = 'http_method_not_allowed', ?\Throwable $previous = null)
    {
        parent::__construct($message, 405, $errorCode, $previous);
    }
}
