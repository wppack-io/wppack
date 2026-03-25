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

namespace WpPack\Component\Security\Tests\Authentication;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Security\Authentication\Passport\Badge\CredentialsBadge;
use WpPack\Component\Security\Authentication\Passport\Badge\RememberMeBadge;
use WpPack\Component\Security\Authentication\Passport\Badge\UserBadge;
use WpPack\Component\Security\Authentication\Passport\Passport;
use WpPack\Component\Security\Authentication\Passport\SelfValidatingPassport;
use WpPack\Component\Security\Exception\AuthenticationException;

final class PassportTest extends TestCase
{
    #[Test]
    public function badgeCanBeAddedAndRetrieved(): void
    {
        $userBadge = new UserBadge('testuser');
        $passport = new Passport($userBadge);
        $rememberMe = new RememberMeBadge(true);

        $passport->addBadge($rememberMe);

        self::assertSame($rememberMe, $passport->getBadge(RememberMeBadge::class));
    }

    #[Test]
    public function hasBadgeReturnsTrueForExistingBadge(): void
    {
        $userBadge = new UserBadge('testuser');
        $credentials = new CredentialsBadge('secret123');
        $passport = new Passport($userBadge, $credentials);

        self::assertTrue($passport->hasBadge(CredentialsBadge::class));
    }

    #[Test]
    public function hasBadgeReturnsFalseForMissingBadge(): void
    {
        $userBadge = new UserBadge('testuser');
        $passport = new Passport($userBadge);

        self::assertFalse($passport->hasBadge(RememberMeBadge::class));
    }

    #[Test]
    public function ensureAllBadgesResolvedPassesWhenAllResolved(): void
    {
        $userBadge = new UserBadge('testuser');
        $credentials = new CredentialsBadge('secret123');
        $credentials->markResolved();
        $passport = new Passport($userBadge, $credentials);

        $passport->ensureAllBadgesResolved();

        // No exception means success
        self::assertTrue(true);
    }

    #[Test]
    public function ensureAllBadgesResolvedThrowsWhenUnresolved(): void
    {
        $userBadge = new UserBadge('testuser');
        $credentials = new CredentialsBadge('secret123');
        $passport = new Passport($userBadge, $credentials);

        $this->expectException(AuthenticationException::class);

        $passport->ensureAllBadgesResolved();
    }

    #[Test]
    public function selfValidatingPassportDoesNotRequireCredentials(): void
    {
        $userBadge = new UserBadge('testuser');
        $passport = new SelfValidatingPassport($userBadge);

        self::assertFalse($passport->hasBadge(CredentialsBadge::class));
        self::assertTrue($passport->hasBadge(UserBadge::class));
    }

    #[Test]
    public function selfValidatingPassportEnsureAllBadgesResolvedPasses(): void
    {
        $userBadge = new UserBadge('testuser');
        $passport = new SelfValidatingPassport($userBadge);

        $passport->ensureAllBadgesResolved();

        self::assertTrue(true);
    }

    #[Test]
    public function credentialsBadgeRejectsEmptyPassword(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Password must not be empty.');

        new CredentialsBadge('');
    }

    #[Test]
    public function credentialsBadgeRejectsOverlyLongPassword(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Password must not exceed 4096 characters.');

        new CredentialsBadge(str_repeat('a', 4097));
    }

    #[Test]
    public function credentialsBadgeRejectsNullBytes(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Password must not contain null bytes.');

        new CredentialsBadge("pass\0word");
    }

    #[Test]
    public function credentialsBadgeCanBeResolved(): void
    {
        $badge = new CredentialsBadge('secret123');

        self::assertFalse($badge->isResolved());

        $badge->markResolved();

        self::assertTrue($badge->isResolved());
    }

    #[Test]
    public function userBadgeRejectsEmptyIdentifier(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('User identifier must not be empty.');

        new UserBadge('');
    }

    #[Test]
    public function userBadgeCallsUserLoader(): void
    {
        $user = new \WP_User();
        $user->ID = 1;
        $user->user_login = 'testuser';

        $badge = new UserBadge('testuser', static fn(string $id): \WP_User => $user);

        self::assertSame($user, $badge->getUser());
    }

    #[Test]
    public function userBadgeThrowsWithoutLoader(): void
    {
        $badge = new UserBadge('testuser');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('No user loader configured.');

        $badge->getUser();
    }

    #[Test]
    public function rememberMeBadgeIsAlwaysResolved(): void
    {
        $badge = new RememberMeBadge();

        self::assertTrue($badge->isResolved());
    }

    #[Test]
    public function rememberMeBadgeTracksEnabled(): void
    {
        $disabled = new RememberMeBadge(false);
        $enabled = new RememberMeBadge(true);

        self::assertFalse($disabled->isEnabled());
        self::assertTrue($enabled->isEnabled());
    }

    #[Test]
    public function userBadgeSetUserOverridesLoader(): void
    {
        $originalUser = new \WP_User();
        $originalUser->ID = 1;
        $originalUser->user_login = 'original';

        $overrideUser = new \WP_User();
        $overrideUser->ID = 2;
        $overrideUser->user_login = 'override';

        $badge = new UserBadge('testuser', static fn(string $id): \WP_User => $originalUser);

        $badge->setUser($overrideUser);

        self::assertSame($overrideUser, $badge->getUser());
        self::assertSame(2, $badge->getUser()->ID);
    }

    #[Test]
    public function userBadgeGetUserIdentifierReturnsIdentifier(): void
    {
        $badge = new UserBadge('test-identifier-123');

        self::assertSame('test-identifier-123', $badge->getUserIdentifier());
    }

    #[Test]
    public function userBadgeGetUserLoaderReturnsClosureOrNull(): void
    {
        $badgeWithLoader = new UserBadge('test', static fn() => new \WP_User());
        $badgeWithoutLoader = new UserBadge('test2');

        self::assertInstanceOf(\Closure::class, $badgeWithLoader->getUserLoader());
        self::assertNull($badgeWithoutLoader->getUserLoader());
    }

    #[Test]
    public function userBadgeIsAlwaysResolved(): void
    {
        $badge = new UserBadge('test');

        self::assertTrue($badge->isResolved());
    }

    #[Test]
    public function passportGetUserReturnsUserFromBadge(): void
    {
        $user = new \WP_User();
        $user->ID = 42;
        $user->user_login = 'testuser';

        $userBadge = new UserBadge('testuser', static fn() => $user);
        $passport = new Passport($userBadge);

        self::assertSame($user, $passport->getUser());
    }

    #[Test]
    public function passportGetUserBadgeReturnsUserBadge(): void
    {
        $userBadge = new UserBadge('testuser');
        $passport = new Passport($userBadge);

        self::assertSame($userBadge, $passport->getUserBadge());
    }

    #[Test]
    public function passportWithCredentialsAndAdditionalBadges(): void
    {
        $userBadge = new UserBadge('testuser');
        $credentials = new CredentialsBadge('secret123');
        $remember = new RememberMeBadge(true);

        $passport = new Passport($userBadge, $credentials, [$remember]);

        self::assertTrue($passport->hasBadge(UserBadge::class));
        self::assertTrue($passport->hasBadge(CredentialsBadge::class));
        self::assertTrue($passport->hasBadge(RememberMeBadge::class));
    }

    #[Test]
    public function passportWithNullCredentials(): void
    {
        $userBadge = new UserBadge('testuser');
        $passport = new Passport($userBadge, null);

        self::assertTrue($passport->hasBadge(UserBadge::class));
        self::assertFalse($passport->hasBadge(CredentialsBadge::class));
    }

    #[Test]
    public function passportGetBadgeReturnsNullForMissingBadge(): void
    {
        $userBadge = new UserBadge('testuser');
        $passport = new Passport($userBadge);

        self::assertNull($passport->getBadge(CredentialsBadge::class));
    }

    #[Test]
    public function passportAddBadgeReturnsSelf(): void
    {
        $userBadge = new UserBadge('testuser');
        $passport = new Passport($userBadge);

        $result = $passport->addBadge(new RememberMeBadge(true));

        self::assertSame($passport, $result);
    }

    #[Test]
    public function credentialsBadgeAcceptsMaxLengthPassword(): void
    {
        $password = str_repeat('a', 4096);
        $badge = new CredentialsBadge($password);

        self::assertSame($password, $badge->getPassword());
    }

    #[Test]
    public function rememberMeBadgeDefaultIsDisabled(): void
    {
        $badge = new RememberMeBadge();

        self::assertFalse($badge->isEnabled());
        self::assertTrue($badge->isResolved());
    }

    #[Test]
    public function userBadgeCachesUserOnSubsequentCalls(): void
    {
        $callCount = 0;
        $user = new \WP_User();
        $user->ID = 1;

        $badge = new UserBadge('testuser', static function () use ($user, &$callCount) {
            $callCount++;

            return $user;
        });

        // First call triggers loader
        $result1 = $badge->getUser();
        // Second call should use cached value
        $result2 = $badge->getUser();

        self::assertSame($user, $result1);
        self::assertSame($user, $result2);
        self::assertSame(1, $callCount);
    }
}
