<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Tests\Authorization;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Security\Authentication\AuthenticationManagerInterface;
use WpPack\Component\Security\Authentication\Token\NullToken;
use WpPack\Component\Security\Authentication\Token\TokenInterface;
use WpPack\Component\Security\Authorization\AuthorizationChecker;
use WpPack\Component\Security\Authorization\Voter\AccessDecisionManager;
use WpPack\Component\Security\Authorization\Voter\VoterInterface;

final class AuthorizationCheckerTest extends TestCase
{
    #[Test]
    public function isGrantedDelegatesToAccessDecisionManager(): void
    {
        $voter = new class implements VoterInterface {
            public function vote(TokenInterface $token, string $attribute, mixed $subject = null): int
            {
                return $attribute === 'allowed' ? self::ACCESS_GRANTED : self::ACCESS_DENIED;
            }
        };

        $adm = new AccessDecisionManager([$voter]);
        $authManager = $this->createAuthManager(null);
        $checker = new AuthorizationChecker($adm, $authManager);

        self::assertTrue($checker->isGranted('allowed'));
        self::assertFalse($checker->isGranted('denied'));
    }

    #[Test]
    public function isGrantedUsesNullTokenWhenNoTokenAvailable(): void
    {
        $voter = new class implements VoterInterface {
            public function vote(TokenInterface $token, string $attribute, mixed $subject = null): int
            {
                return $token->isAuthenticated() ? self::ACCESS_GRANTED : self::ACCESS_DENIED;
            }
        };

        $adm = new AccessDecisionManager([$voter]);
        $authManager = $this->createAuthManager(null);
        $checker = new AuthorizationChecker($adm, $authManager);

        self::assertFalse($checker->isGranted('anything'));
    }

    private function createAuthManager(?TokenInterface $token): AuthenticationManagerInterface
    {
        return new class ($token) implements AuthenticationManagerInterface {
            public function __construct(private readonly ?TokenInterface $token) {}

            public function handleAuthentication(mixed $user, string $username, string $password): mixed
            {
                return $user;
            }

            public function handleStatelessAuthentication(int $userId): int
            {
                return $userId;
            }

            public function getToken(): ?TokenInterface
            {
                return $this->token;
            }
        };
    }
}
