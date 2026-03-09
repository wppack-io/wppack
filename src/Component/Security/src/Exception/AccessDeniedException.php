<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Exception;

class AccessDeniedException extends \RuntimeException
{
    public function __construct(
        string $message = 'Access Denied.',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
