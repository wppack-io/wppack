<?php

declare(strict_types=1);

namespace WpPack\Component\Handler\Exception;

class SecurityException extends HandlerException
{
    public function __construct(string $message)
    {
        parent::__construct($message, 403);
    }
}
