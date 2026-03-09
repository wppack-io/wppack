<?php

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
        };
    }
}
