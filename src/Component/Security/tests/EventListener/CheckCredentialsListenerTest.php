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

namespace WPPack\Component\Security\Tests\EventListener;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Security\Authentication\Passport\Badge\CredentialsBadge;
use WPPack\Component\Security\Authentication\Passport\Badge\UserBadge;
use WPPack\Component\Security\Authentication\Passport\Passport;
use WPPack\Component\Security\Authentication\Passport\SelfValidatingPassport;
use WPPack\Component\Security\Event\CheckPassportEvent;
use WPPack\Component\Security\EventListener\CheckCredentialsListener;
use WPPack\Component\Security\Exception\InvalidCredentialsException;

#[CoversClass(CheckCredentialsListener::class)]
final class CheckCredentialsListenerTest extends TestCase
{
    private CheckCredentialsListener $listener;

    protected function setUp(): void
    {
        $this->listener = new CheckCredentialsListener();
    }

    #[Test]
    public function validPasswordResolvesCredentialsBadge(): void
    {
        $password = 'correct-password-123';

        // Create a WP_User with a hashed password
        $user = new \WP_User();
        $user->ID = 1;
        $user->user_login = 'testuser';
        $user->user_pass = wp_hash_password($password);

        $userBadge = new UserBadge($user->user_login, static fn() => $user);
        $credentialsBadge = new CredentialsBadge($password);
        $passport = new Passport($userBadge, $credentialsBadge);

        $authenticator = $this->createStub(\WPPack\Component\Security\Authentication\AuthenticatorInterface::class);
        $event = new CheckPassportEvent($authenticator, $passport);

        self::assertFalse($credentialsBadge->isResolved());

        ($this->listener)($event);

        self::assertTrue($credentialsBadge->isResolved());
    }

    #[Test]
    public function invalidPasswordThrowsInvalidCredentialsException(): void
    {
        $user = new \WP_User();
        $user->ID = 2;
        $user->user_login = 'testuser2';
        $user->user_pass = wp_hash_password('correct-password');

        $userBadge = new UserBadge($user->user_login, static fn() => $user);
        $credentialsBadge = new CredentialsBadge('wrong-password');
        $passport = new Passport($userBadge, $credentialsBadge);

        $authenticator = $this->createStub(\WPPack\Component\Security\Authentication\AuthenticatorInterface::class);
        $event = new CheckPassportEvent($authenticator, $passport);

        $this->expectException(InvalidCredentialsException::class);

        ($this->listener)($event);
    }

    #[Test]
    public function skipsBadgeWhenNoCredentialsBadgePresent(): void
    {
        $userBadge = new UserBadge('test-user');
        $passport = new SelfValidatingPassport($userBadge);

        $authenticator = $this->createStub(\WPPack\Component\Security\Authentication\AuthenticatorInterface::class);
        $event = new CheckPassportEvent($authenticator, $passport);

        // Should not throw, simply returns
        ($this->listener)($event);

        // No credentials badge exists, nothing to resolve
        self::assertFalse($passport->hasBadge(CredentialsBadge::class));
    }
}
