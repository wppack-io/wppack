<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Event;

use WpPack\Component\EventDispatcher\Event;
use WpPack\Component\Security\Exception\AuthenticationException;

final class AuthenticationFailureEvent extends Event
{
    public function __construct(
        private readonly AuthenticationException $exception,
    ) {}

    public function getException(): AuthenticationException
    {
        return $this->exception;
    }
}
