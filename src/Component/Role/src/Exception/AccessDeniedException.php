<?php

declare(strict_types=1);

namespace WpPack\Component\Role\Exception;

class AccessDeniedException extends \RuntimeException implements ExceptionInterface
{
    public function __construct(
        string $message = 'Access Denied.',
        int $code = 403,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
