<?php

declare(strict_types=1);

namespace WpPack\Component\Role\Exception;

class AccessDeniedException extends \RuntimeException implements ExceptionInterface
{
    public function __construct(
        string $message = 'Access Denied.',
        public readonly int $statusCode = 403,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
