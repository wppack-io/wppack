<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Tests\Authentication\Passport;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Security\Authentication\Passport\Badge\BadgeInterface;
use WpPack\Component\Security\Authentication\Passport\Badge\CredentialsBadge;
use WpPack\Component\Security\Authentication\Passport\Badge\RememberMeBadge;
use WpPack\Component\Security\Authentication\Passport\Badge\UserBadge;
use WpPack\Component\Security\Authentication\Passport\Passport;
use WpPack\Component\Security\Authentication\Passport\SelfValidatingPassport;
use WpPack\Component\Security\Exception\AuthenticationException;

final class SelfValidatingPassportTest extends TestCase
{
    // ---------------------------------------------------------------
    // SelfValidatingPassport creation
    // ---------------------------------------------------------------

    #[Test]
    public function selfValidatingPassportExtendsPassport(): void
    {
        $passport = new SelfValidatingPassport(new UserBadge('testuser'));

        self::assertInstanceOf(Passport::class, $passport);
    }

    #[Test]
    public function selfValidatingPassportCreatedWithUserBadgeOnly(): void
    {
        $userBadge = new UserBadge('admin');
        $passport = new SelfValidatingPassport($userBadge);

        self::assertTrue($passport->hasBadge(UserBadge::class));
        self::assertSame($userBadge, $passport->getBadge(UserBadge::class));
    }

    #[Test]
    public function selfValidatingPassportDoesNotHaveCredentialsBadge(): void
    {
        $passport = new SelfValidatingPassport(new UserBadge('oauth_user'));

        self::assertFalse($passport->hasBadge(CredentialsBadge::class));
    }

    #[Test]
    public function selfValidatingPassportCreatedWithAdditionalBadges(): void
    {
        $rememberMe = new RememberMeBadge(true);
        $passport = new SelfValidatingPassport(
            new UserBadge('saml_user'),
            [$rememberMe],
        );

        self::assertTrue($passport->hasBadge(UserBadge::class));
        self::assertTrue($passport->hasBadge(RememberMeBadge::class));
        self::assertSame($rememberMe, $passport->getBadge(RememberMeBadge::class));
    }

    #[Test]
    public function selfValidatingPassportCreatedWithMultipleBadges(): void
    {
        $rememberMe = new RememberMeBadge(false);
        $customBadge = new class implements BadgeInterface {
            public function isResolved(): bool
            {
                return true;
            }
        };

        $passport = new SelfValidatingPassport(
            new UserBadge('multi_badge_user'),
            [$rememberMe, $customBadge],
        );

        self::assertTrue($passport->hasBadge(UserBadge::class));
        self::assertTrue($passport->hasBadge(RememberMeBadge::class));
        self::assertTrue($passport->hasBadge($customBadge::class));
    }

    // ---------------------------------------------------------------
    // SelfValidatingPassport user access
    // ---------------------------------------------------------------

    #[Test]
    public function selfValidatingPassportReturnsUserFromUserBadge(): void
    {
        $user = new \WP_User();
        $user->ID = 99;
        $user->user_login = 'oauth_user';

        $userBadge = new UserBadge('oauth_user', static fn(string $id): \WP_User => $user);
        $passport = new SelfValidatingPassport($userBadge);

        self::assertSame($user, $passport->getUser());
    }

    #[Test]
    public function selfValidatingPassportReturnsUserBadge(): void
    {
        $userBadge = new UserBadge('saml_user');
        $passport = new SelfValidatingPassport($userBadge);

        self::assertSame($userBadge, $passport->getUserBadge());
    }

    #[Test]
    public function selfValidatingPassportUserBadgeSetUserWorks(): void
    {
        $user = new \WP_User();
        $user->ID = 50;
        $user->user_login = 'set_user';

        $userBadge = new UserBadge('set_user');
        $userBadge->setUser($user);

        $passport = new SelfValidatingPassport($userBadge);

        self::assertSame($user, $passport->getUser());
        self::assertSame(50, $passport->getUser()->ID);
    }

    // ---------------------------------------------------------------
    // SelfValidatingPassport badge operations
    // ---------------------------------------------------------------

    #[Test]
    public function selfValidatingPassportAddBadgeAfterCreation(): void
    {
        $passport = new SelfValidatingPassport(new UserBadge('testuser'));

        self::assertFalse($passport->hasBadge(RememberMeBadge::class));

        $passport->addBadge(new RememberMeBadge(true));

        self::assertTrue($passport->hasBadge(RememberMeBadge::class));
    }

    #[Test]
    public function selfValidatingPassportGetBadgeReturnsNullForMissingBadge(): void
    {
        $passport = new SelfValidatingPassport(new UserBadge('testuser'));

        self::assertNull($passport->getBadge(RememberMeBadge::class));
    }

    #[Test]
    public function selfValidatingPassportBadgeOverwritesSameType(): void
    {
        $badge1 = new RememberMeBadge(false);
        $badge2 = new RememberMeBadge(true);

        $passport = new SelfValidatingPassport(
            new UserBadge('testuser'),
            [$badge1],
        );

        self::assertFalse($passport->getBadge(RememberMeBadge::class)?->isEnabled());

        $passport->addBadge($badge2);

        self::assertTrue($passport->getBadge(RememberMeBadge::class)?->isEnabled());
        self::assertSame($badge2, $passport->getBadge(RememberMeBadge::class));
    }

    // ---------------------------------------------------------------
    // SelfValidatingPassport ensureAllBadgesResolved
    // ---------------------------------------------------------------

    #[Test]
    public function selfValidatingPassportEnsureAllBadgesResolvedWithNoBadges(): void
    {
        $passport = new SelfValidatingPassport(new UserBadge('testuser'));

        // UserBadge.isResolved() always returns true, so this should pass
        $passport->ensureAllBadgesResolved();

        self::assertTrue(true);
    }

    #[Test]
    public function selfValidatingPassportEnsureAllBadgesResolvedWithResolvedBadges(): void
    {
        $passport = new SelfValidatingPassport(
            new UserBadge('testuser'),
            [new RememberMeBadge(true)],
        );

        // Both UserBadge and RememberMeBadge are always resolved
        $passport->ensureAllBadgesResolved();

        self::assertTrue(true);
    }

    #[Test]
    public function selfValidatingPassportEnsureAllBadgesResolvedThrowsOnUnresolved(): void
    {
        $unresolvedBadge = new class implements BadgeInterface {
            public function isResolved(): bool
            {
                return false;
            }
        };

        $passport = new SelfValidatingPassport(
            new UserBadge('testuser'),
            [$unresolvedBadge],
        );

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('has not been resolved');

        $passport->ensureAllBadgesResolved();
    }

    #[Test]
    public function selfValidatingPassportEnsureAllBadgesResolvedExceptionIncludesBadgeClass(): void
    {
        $unresolvedBadge = new class implements BadgeInterface {
            public function isResolved(): bool
            {
                return false;
            }
        };

        $passport = new SelfValidatingPassport(
            new UserBadge('testuser'),
            [$unresolvedBadge],
        );

        try {
            $passport->ensureAllBadgesResolved();
            self::fail('Expected AuthenticationException was not thrown.');
        } catch (AuthenticationException $e) {
            // The error message should reference the badge class and include guidance
            self::assertStringContainsString('BadgeInterface@anonymous', $e->getMessage());
            self::assertStringContainsString('Did you forget to register the required event listener?', $e->getMessage());
        }
    }

    // ---------------------------------------------------------------
    // RememberMeBadge
    // ---------------------------------------------------------------

    #[Test]
    public function rememberMeBadgeImplementsBadgeInterface(): void
    {
        $badge = new RememberMeBadge();

        self::assertInstanceOf(BadgeInterface::class, $badge);
    }

    #[Test]
    public function rememberMeBadgeIsDisabledByDefault(): void
    {
        $badge = new RememberMeBadge();

        self::assertFalse($badge->isEnabled());
    }

    #[Test]
    public function rememberMeBadgeCanBeExplicitlyDisabled(): void
    {
        $badge = new RememberMeBadge(false);

        self::assertFalse($badge->isEnabled());
    }

    #[Test]
    public function rememberMeBadgeCanBeEnabled(): void
    {
        $badge = new RememberMeBadge(true);

        self::assertTrue($badge->isEnabled());
    }

    #[Test]
    public function rememberMeBadgeIsAlwaysResolved(): void
    {
        $disabled = new RememberMeBadge(false);
        $enabled = new RememberMeBadge(true);

        self::assertTrue($disabled->isResolved());
        self::assertTrue($enabled->isResolved());
    }

    #[Test]
    public function rememberMeBadgeResolvedRegardlessOfEnabledState(): void
    {
        // RememberMeBadge does not require external resolution
        $badge = new RememberMeBadge();

        self::assertTrue($badge->isResolved());
        self::assertFalse($badge->isEnabled());

        // isResolved is always true regardless of enabled state
        $enabledBadge = new RememberMeBadge(true);

        self::assertTrue($enabledBadge->isResolved());
        self::assertTrue($enabledBadge->isEnabled());
    }

    #[Test]
    public function rememberMeBadgeEnabledStateIsImmutable(): void
    {
        $badge = new RememberMeBadge(true);

        // Accessing multiple times should return the same result
        self::assertTrue($badge->isEnabled());
        self::assertTrue($badge->isEnabled());
    }

    #[Test]
    public function rememberMeBadgeDoesNotBlockPassportResolution(): void
    {
        $passport = new SelfValidatingPassport(
            new UserBadge('testuser'),
            [new RememberMeBadge(true)],
        );

        // This should not throw because RememberMeBadge is always resolved
        $passport->ensureAllBadgesResolved();

        self::assertTrue(true);
    }
}
