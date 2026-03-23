<?php

declare(strict_types=1);

namespace WpPack\Component\Security\Tests\ValueResolver;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Security\Attribute\CurrentUser;
use WpPack\Component\Security\Authentication\AuthenticationManagerInterface;
use WpPack\Component\Security\Authentication\Token\TokenInterface;
use WpPack\Component\Security\Authorization\AuthorizationCheckerInterface;
use WpPack\Component\Security\Security;
use WpPack\Component\Security\ValueResolver\CurrentUserValueResolver;

final class CurrentUserValueResolverTest extends TestCase
{
    #[Test]
    public function supportsParameterWithCurrentUserAttribute(): void
    {
        $resolver = new CurrentUserValueResolver($this->createSecurityMock());

        $param = new \ReflectionParameter(
            static fn(#[CurrentUser] \WP_User $user) => null,
            'user',
        );

        self::assertTrue($resolver->supports($param));
    }

    #[Test]
    public function doesNotSupportParameterWithoutAttribute(): void
    {
        $resolver = new CurrentUserValueResolver($this->createSecurityMock());

        $param = new \ReflectionParameter(
            static fn(\WP_User $user) => null,
            'user',
        );

        self::assertFalse($resolver->supports($param));
    }

    #[Test]
    public function resolvesAuthenticatedUser(): void
    {
        wp_set_current_user(1);
        $user = wp_get_current_user();

        $resolver = new CurrentUserValueResolver($this->createSecurityMock($user));

        $param = new \ReflectionParameter(
            static fn(#[CurrentUser] \WP_User $user) => null,
            'user',
        );

        self::assertSame($user, $resolver->resolve($param));
    }

    #[Test]
    public function resolvesNullWhenNoUser(): void
    {
        $resolver = new CurrentUserValueResolver($this->createSecurityMock());

        $param = new \ReflectionParameter(
            static fn(#[CurrentUser] ?\WP_User $user) => null,
            'user',
        );

        self::assertNull($resolver->resolve($param));
    }

    private function createSecurityMock(?\WP_User $user = null): Security
    {
        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authManager = $this->createMock(AuthenticationManagerInterface::class);

        if ($user !== null) {
            $token = $this->createMock(TokenInterface::class);
            $token->method('isAuthenticated')->willReturn(true);
            $token->method('getUser')->willReturn($user);
            $authManager->method('getToken')->willReturn($token);
        }

        return new Security($authChecker, $authManager);
    }
}
