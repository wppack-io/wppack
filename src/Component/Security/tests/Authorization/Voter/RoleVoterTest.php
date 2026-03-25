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

namespace WpPack\Component\Security\Tests\Authorization\Voter;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Security\Authentication\Token\NullToken;
use WpPack\Component\Security\Authentication\Token\TokenInterface;
use WpPack\Component\Security\Authorization\Voter\RoleVoter;
use WpPack\Component\Security\Authorization\Voter\VoterInterface;

final class RoleVoterTest extends TestCase
{
    private RoleVoter $voter;

    protected function setUp(): void
    {
        $this->voter = new RoleVoter();
    }

    #[Test]
    public function abstainForNonRoleAttribute(): void
    {
        $token = new NullToken();

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $this->voter->vote($token, 'edit_posts'));
    }

    #[Test]
    public function grantedForUserWithRole(): void
    {
        $token = $this->createTokenWithRoles(['administrator']);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, 'ROLE_ADMINISTRATOR'));
    }

    #[Test]
    public function deniedForUserWithoutRole(): void
    {
        $token = $this->createTokenWithRoles(['subscriber']);

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, 'ROLE_EDITOR'));
    }

    #[Test]
    public function deniedForUnauthenticatedToken(): void
    {
        $token = new NullToken();

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, 'ROLE_ADMINISTRATOR'));
    }

    #[Test]
    public function roleComparisonIsCaseInsensitive(): void
    {
        $token = $this->createTokenWithRoles(['Administrator']);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, 'ROLE_ADMINISTRATOR'));
    }

    #[Test]
    public function superAdminGrantedForAdminOnSingleSite(): void
    {
        $userId = wp_insert_user([
            'user_login' => 'test_admin_' . uniqid(),
            'user_pass' => wp_generate_password(),
            'role' => 'administrator',
        ]);
        $user = new \WP_User($userId);

        $token = $this->createTokenWithUser($user);

        // On single-site, administrators have delete_users capability, so is_super_admin() returns true
        self::assertSame(VoterInterface::ACCESS_GRANTED, $this->voter->vote($token, 'ROLE_SUPER_ADMIN'));
    }

    #[Test]
    public function superAdminDeniedForSubscriber(): void
    {
        $userId = wp_insert_user([
            'user_login' => 'test_subscriber_' . uniqid(),
            'user_pass' => wp_generate_password(),
            'role' => 'subscriber',
        ]);
        $user = new \WP_User($userId);

        $token = $this->createTokenWithUser($user);

        self::assertSame(VoterInterface::ACCESS_DENIED, $this->voter->vote($token, 'ROLE_SUPER_ADMIN'));
    }

    /**
     * @param list<string> $roles
     */
    private function createTokenWithRoles(array $roles): TokenInterface
    {
        return new class ($roles) implements TokenInterface {
            /** @param list<string> $roles */
            public function __construct(private readonly array $roles) {}

            public function getUser(): \WP_User
            {
                throw new \LogicException('Not needed for this test.');
            }

            public function getRoles(): array
            {
                return $this->roles;
            }

            public function isAuthenticated(): bool
            {
                return true;
            }

            public function getBlogId(): ?int
            {
                return null;
            }
        };
    }

    private function createTokenWithUser(\WP_User $user): TokenInterface
    {
        return new class ($user) implements TokenInterface {
            public function __construct(private readonly \WP_User $user) {}

            public function getUser(): \WP_User
            {
                return $this->user;
            }

            public function getRoles(): array
            {
                return $this->user->roles;
            }

            public function isAuthenticated(): bool
            {
                return true;
            }

            public function getBlogId(): ?int
            {
                return null;
            }
        };
    }
}
