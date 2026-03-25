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
use WpPack\Component\Security\Authentication\AuthenticatorInterface;
use WpPack\Component\Security\Authentication\Passport\Passport;

final class CheckPassportEvent extends Event
{
    public function __construct(
        private readonly AuthenticatorInterface $authenticator,
        private readonly Passport $passport,
    ) {}

    public function getAuthenticator(): AuthenticatorInterface
    {
        return $this->authenticator;
    }

    public function getPassport(): Passport
    {
        return $this->passport;
    }
}
