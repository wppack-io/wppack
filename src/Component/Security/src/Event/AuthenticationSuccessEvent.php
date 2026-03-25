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
