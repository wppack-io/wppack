<?php

/*
 * This file is part of the WPPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WPPack\Component\Security\Tests\Event;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Security\Authentication\AuthenticatorInterface;
use WPPack\Component\Security\Authentication\Passport\Badge\UserBadge;
use WPPack\Component\Security\Authentication\Passport\Passport;
use WPPack\Component\Security\Authentication\Token\NullToken;
use WPPack\Component\Security\Event\AuthenticationFailureEvent;
use WPPack\Component\Security\Event\AuthenticationSuccessEvent;
use WPPack\Component\Security\Event\CheckPassportEvent;
use WPPack\Component\Security\Event\LoginSuccessEvent;
use WPPack\Component\Security\Event\LogoutEvent;
use WPPack\Component\Security\Exception\AuthenticationException;

final class SecurityEventsTest extends TestCase
{
    #[Test]
    public function checkPassportEventHoldsAuthenticatorAndPassport(): void
    {
        $authenticator = $this->createStub(AuthenticatorInterface::class);
        $passport = new Passport(new UserBadge('testuser'));

        $event = new CheckPassportEvent($authenticator, $passport);

        self::assertSame($authenticator, $event->getAuthenticator());
        self::assertSame($passport, $event->getPassport());
    }

    #[Test]
    public function authenticationSuccessEventHoldsToken(): void
    {
        $token = new NullToken();
        $event = new AuthenticationSuccessEvent($token);

        self::assertSame($token, $event->getToken());
    }

    #[Test]
    public function authenticationFailureEventHoldsException(): void
    {
        $exception = new AuthenticationException('Test failure.');
        $event = new AuthenticationFailureEvent($exception);

        self::assertSame($exception, $event->getException());
    }

    #[Test]
    public function loginSuccessEventHoldsUserAndUsername(): void
    {
        $user = new \WP_User();
        $user->ID = 1;
        $user->user_login = 'admin';

        $event = new LoginSuccessEvent($user, 'admin');

        self::assertSame($user, $event->getUser());
        self::assertSame('admin', $event->getUsername());
    }

    #[Test]
    public function logoutEventHoldsUserId(): void
    {
        $event = new LogoutEvent(42);

        self::assertSame(42, $event->getUserId());
    }
}
