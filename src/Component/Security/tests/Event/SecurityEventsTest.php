<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Tests\Event;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Security\Authentication\AuthenticatorInterface;
use WpPack\Component\Security\Authentication\Passport\Badge\UserBadge;
use WpPack\Component\Security\Authentication\Passport\Passport;
use WpPack\Component\Security\Authentication\Token\NullToken;
use WpPack\Component\Security\Event\AuthenticationFailureEvent;
use WpPack\Component\Security\Event\AuthenticationSuccessEvent;
use WpPack\Component\Security\Event\CheckPassportEvent;
use WpPack\Component\Security\Event\LoginSuccessEvent;
use WpPack\Component\Security\Event\LogoutEvent;
use WpPack\Component\Security\Exception\AuthenticationException;

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
        if (!class_exists(\WP_User::class)) {
            self::markTestSkipped('WP_User class is not available.');
        }

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
