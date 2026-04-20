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

namespace WPPack\Component\Security\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Security\Attribute\AsAuthenticator;
use WPPack\Component\Security\Attribute\AsVoter;
use WPPack\Component\Security\Attribute\CurrentUser;
use WPPack\Component\Security\Authentication\Passport\Badge\CredentialsBadge;
use WPPack\Component\Security\Authentication\Passport\Badge\RememberMeBadge;
use WPPack\Component\Security\Authentication\Passport\Badge\UserBadge;
use WPPack\Component\Security\Authentication\Passport\Passport;
use WPPack\Component\Security\Authentication\Passport\SelfValidatingPassport;
use WPPack\Component\Security\Authentication\Token\TokenInterface;
use WPPack\Component\Security\Event\AuthenticationFailureEvent;
use WPPack\Component\Security\Event\AuthenticationSuccessEvent;
use WPPack\Component\Security\Event\CheckPassportEvent;
use WPPack\Component\Security\Event\LoginSuccessEvent;
use WPPack\Component\Security\Event\LogoutEvent;
use WPPack\Component\Security\Exception\AuthenticationException;
use WPPack\Component\Security\Exception\InvalidCredentialsException;
use WPPack\Component\Security\Exception\UserNotFoundException;

#[CoversClass(AsAuthenticator::class)]
#[CoversClass(AsVoter::class)]
#[CoversClass(CurrentUser::class)]
#[CoversClass(AuthenticationFailureEvent::class)]
#[CoversClass(AuthenticationSuccessEvent::class)]
#[CoversClass(CheckPassportEvent::class)]
#[CoversClass(LoginSuccessEvent::class)]
#[CoversClass(LogoutEvent::class)]
#[CoversClass(AuthenticationException::class)]
#[CoversClass(InvalidCredentialsException::class)]
#[CoversClass(UserNotFoundException::class)]
#[CoversClass(UserBadge::class)]
#[CoversClass(RememberMeBadge::class)]
#[CoversClass(CredentialsBadge::class)]
#[CoversClass(Passport::class)]
#[CoversClass(SelfValidatingPassport::class)]
final class SecurityDtoTest extends TestCase
{
    private function makeUser(string $prefix = 'security_dto'): \WP_User
    {
        $userId = (int) \wp_insert_user([
            'user_login' => $prefix . '_' . \uniqid(),
            'user_email' => $prefix . '_' . \uniqid() . '@example.com',
            'user_pass' => \wp_generate_password(),
        ]);

        return new \WP_User($userId);
    }

    // ── Attributes ──────────────────────────────────────────────────

    #[Test]
    public function asAuthenticatorAttributeStoresPriority(): void
    {
        self::assertSame(0, (new AsAuthenticator())->priority);
        self::assertSame(42, (new AsAuthenticator(priority: 42))->priority);
    }

    #[Test]
    public function asVoterAttributeStoresPriority(): void
    {
        self::assertSame(0, (new AsVoter())->priority);
        self::assertSame(10, (new AsVoter(priority: 10))->priority);
    }

    #[Test]
    public function currentUserAttributeIsConstructible(): void
    {
        self::assertInstanceOf(CurrentUser::class, new CurrentUser());
    }

    // ── Events ──────────────────────────────────────────────────────

    #[Test]
    public function authenticationSuccessEventCarriesToken(): void
    {
        $token = $this->createMock(TokenInterface::class);
        $event = new AuthenticationSuccessEvent($token);

        self::assertSame($token, $event->getToken());
    }

    #[Test]
    public function authenticationFailureEventCarriesException(): void
    {
        $exception = new AuthenticationException('boom');
        $event = new AuthenticationFailureEvent($exception);

        self::assertSame($exception, $event->getException());
    }

    #[Test]
    public function loginSuccessEventCarriesUserAndUsername(): void
    {
        $user = $this->makeUser();
        $event = new LoginSuccessEvent($user, 'alice');

        self::assertSame($user, $event->getUser());
        self::assertSame('alice', $event->getUsername());
    }

    #[Test]
    public function logoutEventCarriesUserId(): void
    {
        self::assertSame(42, (new LogoutEvent(42))->getUserId());
    }

    #[Test]
    public function checkPassportEventCarriesAuthenticatorAndPassport(): void
    {
        $passport = new Passport(new UserBadge('alice'));
        $authenticator = $this->createMock(\WPPack\Component\Security\Authentication\AuthenticatorInterface::class);
        $event = new CheckPassportEvent($authenticator, $passport);

        self::assertSame($authenticator, $event->getAuthenticator());
        self::assertSame($passport, $event->getPassport());
    }

    // ── Exceptions ──────────────────────────────────────────────────

    #[Test]
    public function authenticationExceptionSafeMessageIsGeneric(): void
    {
        $exception = new AuthenticationException('internal detail');

        self::assertSame('internal detail', $exception->getMessage());
        self::assertSame('Authentication failed.', $exception->getSafeMessage(), 'safe message must not leak internals');
    }

    #[Test]
    public function invalidCredentialsExceptionExtendsAuthentication(): void
    {
        $exception = new InvalidCredentialsException();

        self::assertInstanceOf(AuthenticationException::class, $exception);
        self::assertSame('Invalid credentials.', $exception->getMessage());
    }

    #[Test]
    public function userNotFoundExceptionCarriesIdentifier(): void
    {
        $exception = new UserNotFoundException();
        $exception->setUserIdentifier('alice@example.com');

        self::assertInstanceOf(AuthenticationException::class, $exception);
        self::assertSame('alice@example.com', $exception->getUserIdentifier());
        self::assertSame('', (new UserNotFoundException())->getUserIdentifier(), 'default identifier is empty');
    }

    // ── Badges ──────────────────────────────────────────────────────

    #[Test]
    public function userBadgeRejectsEmptyIdentifier(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new UserBadge('');
    }

    #[Test]
    public function userBadgeRequiresLoaderOrSetter(): void
    {
        $this->expectException(\LogicException::class);
        (new UserBadge('alice'))->getUser();
    }

    #[Test]
    public function userBadgeSetUserBypassesLoader(): void
    {
        $user = $this->makeUser('badge_set');
        $badge = new UserBadge('alice');
        $badge->setUser($user);

        self::assertSame($user, $badge->getUser());
    }

    #[Test]
    public function userBadgeLazyLoadsViaClosure(): void
    {
        $user = $this->makeUser('badge_load');
        $calls = 0;
        $badge = new UserBadge('alice', function (string $id) use (&$calls, $user): \WP_User {
            $calls++;
            self::assertSame('alice', $id);

            return $user;
        });

        self::assertSame($user, $badge->getUser());
        self::assertSame($user, $badge->getUser(), 'second call must be cached');
        self::assertSame(1, $calls, 'loader invoked exactly once');
    }

    #[Test]
    public function userBadgeExposesIdentifierAndLoader(): void
    {
        $closure = static fn(): \WP_User => throw new \LogicException('unused');
        $badge = new UserBadge('alice', $closure);

        self::assertSame('alice', $badge->getUserIdentifier());
        self::assertSame($closure, $badge->getUserLoader());
        self::assertTrue($badge->isResolved());
    }

    #[Test]
    public function rememberMeBadgeIsOptInDefaultOff(): void
    {
        self::assertFalse((new RememberMeBadge())->isEnabled());
        self::assertTrue((new RememberMeBadge(true))->isEnabled());
        self::assertTrue((new RememberMeBadge())->isResolved());
    }

    #[Test]
    public function credentialsBadgeRequiresNonEmptyPassword(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CredentialsBadge('');
    }

    #[Test]
    public function credentialsBadgeRejectsOverlongPassword(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CredentialsBadge(str_repeat('a', 4097));
    }

    #[Test]
    public function credentialsBadgeRejectsNullByte(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new CredentialsBadge("abc\0xyz");
    }

    #[Test]
    public function credentialsBadgeMarksResolvedExplicitly(): void
    {
        $badge = new CredentialsBadge('hunter2hunter2');

        self::assertFalse($badge->isResolved());
        $badge->markResolved();
        self::assertTrue($badge->isResolved());
        self::assertSame('hunter2hunter2', $badge->getPassword());
    }

    // ── Passport ────────────────────────────────────────────────────

    #[Test]
    public function passportRegistersAllBadgesByClass(): void
    {
        $user = $this->makeUser('passport');
        $userBadge = new UserBadge('alice');
        $userBadge->setUser($user);
        $creds = new CredentialsBadge('hunter2hunter2');
        $creds->markResolved();
        $remember = new RememberMeBadge(true);

        $passport = new Passport($userBadge, $creds, [$remember]);

        self::assertTrue($passport->hasBadge(UserBadge::class));
        self::assertTrue($passport->hasBadge(CredentialsBadge::class));
        self::assertTrue($passport->hasBadge(RememberMeBadge::class));
        self::assertSame($user, $passport->getUser());
        self::assertSame($userBadge, $passport->getUserBadge());
        self::assertSame($creds, $passport->getBadge(CredentialsBadge::class));
        self::assertNull($passport->getBadge(RememberMeBadge::class)?->isEnabled() === null ? RememberMeBadge::class : null, 'unused expression');
        self::assertSame($remember, $passport->getBadge(RememberMeBadge::class));
    }

    #[Test]
    public function passportWithoutCredentialsBadgeStillOk(): void
    {
        $passport = new Passport(new UserBadge('alice'));

        self::assertTrue($passport->hasBadge(UserBadge::class));
        self::assertFalse($passport->hasBadge(CredentialsBadge::class));
    }

    #[Test]
    public function passportEnsureAllBadgesResolvedThrowsWhenUnresolvedCreds(): void
    {
        $userBadge = new UserBadge('alice');
        $userBadge->setUser($this->makeUser('passport_unresolved'));
        $creds = new CredentialsBadge('hunter2hunter2'); // NOT marked resolved

        $passport = new Passport($userBadge, $creds);

        $this->expectException(AuthenticationException::class);
        $passport->ensureAllBadgesResolved();
    }

    #[Test]
    public function passportEnsureAllBadgesResolvedPassesWhenBadgesResolved(): void
    {
        $userBadge = new UserBadge('alice');
        $userBadge->setUser($this->makeUser('passport_resolved'));
        $creds = new CredentialsBadge('hunter2hunter2');
        $creds->markResolved();

        $passport = new Passport($userBadge, $creds);
        $passport->ensureAllBadgesResolved();

        self::assertTrue(true, 'no exception thrown');
    }

    #[Test]
    public function passportAddBadgeReplacesSameClassEntry(): void
    {
        $passport = new Passport(new UserBadge('alice'));
        $first = new RememberMeBadge(false);
        $second = new RememberMeBadge(true);

        $passport->addBadge($first);
        $passport->addBadge($second);

        self::assertSame($second, $passport->getBadge(RememberMeBadge::class));
    }

    #[Test]
    public function selfValidatingPassportHasNoCredentialsBadge(): void
    {
        $passport = new SelfValidatingPassport(new UserBadge('alice'));

        self::assertInstanceOf(Passport::class, $passport);
        self::assertFalse($passport->hasBadge(CredentialsBadge::class));
    }

    #[Test]
    public function selfValidatingPassportPropagatesAdditionalBadges(): void
    {
        $remember = new RememberMeBadge(true);
        $passport = new SelfValidatingPassport(new UserBadge('alice'), [$remember]);

        self::assertTrue($passport->hasBadge(RememberMeBadge::class));
        self::assertSame($remember, $passport->getBadge(RememberMeBadge::class));
    }
}
