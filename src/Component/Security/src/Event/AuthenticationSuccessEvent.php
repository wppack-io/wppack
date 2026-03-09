<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Event;

use WpPack\Component\EventDispatcher\Event;
use WpPack\Component\Security\Authentication\Token\TokenInterface;

final class AuthenticationSuccessEvent extends Event
{
    public function __construct(
        private readonly TokenInterface $token,
    ) {}

    public function getToken(): TokenInterface
    {
        return $this->token;
    }
}
