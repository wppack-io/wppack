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

namespace WPPack\Component\Security\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\Role\Authorization\IsGrantedChecker;
use WPPack\Component\Security\Authentication\AuthenticationManager;
use WPPack\Component\Security\Authentication\AuthenticationManagerInterface;
use WPPack\Component\Security\Authentication\Provider\UserProviderInterface;
use WPPack\Component\Security\Authentication\Provider\WordPressUserProvider;
use WPPack\Component\Security\AuthenticationSession;
use WPPack\Component\Security\Authorization\AuthorizationChecker;
use WPPack\Component\Security\Authorization\AuthorizationCheckerInterface;
use WPPack\Component\Security\Authorization\Voter\AccessDecisionManager;
use WPPack\Component\Security\Authorization\Voter\CapabilityVoter;
use WPPack\Component\Security\Authorization\Voter\RoleVoter;
use WPPack\Component\Security\DependencyInjection\SecurityServiceProvider;
use WPPack\Component\Security\EventListener\CheckCredentialsListener;
use WPPack\Component\Security\Security;
use WPPack\Component\User\UserRepository;
use WPPack\Component\User\UserRepositoryInterface;

#[CoversClass(SecurityServiceProvider::class)]
final class SecurityServiceProviderTest extends TestCase
{
    #[Test]
    public function registersCoreAndAuthAndAuthzServices(): void
    {
        $builder = new ContainerBuilder();

        (new SecurityServiceProvider())->register($builder);

        foreach ([
            UserRepository::class,
            WordPressUserProvider::class,
            AuthenticationSession::class,
            AuthenticationManager::class,
            CapabilityVoter::class,
            RoleVoter::class,
            AccessDecisionManager::class,
            AuthorizationChecker::class,
            Security::class,
            IsGrantedChecker::class,
            CheckCredentialsListener::class,
        ] as $id) {
            self::assertTrue($builder->hasDefinition($id), "definition missing: {$id}");
        }
    }

    #[Test]
    public function interfaceAliasesAreRegistered(): void
    {
        $builder = new ContainerBuilder();

        (new SecurityServiceProvider())->register($builder);

        $symfony = $builder->getSymfonyBuilder();

        self::assertSame(UserRepository::class, (string) $symfony->getAlias(UserRepositoryInterface::class));
        self::assertSame(WordPressUserProvider::class, (string) $symfony->getAlias(UserProviderInterface::class));
        self::assertSame(AuthenticationManager::class, (string) $symfony->getAlias(AuthenticationManagerInterface::class));
        self::assertSame(AuthorizationChecker::class, (string) $symfony->getAlias(AuthorizationCheckerInterface::class));
    }

    #[Test]
    public function votersAreTaggedAsSecurityVoter(): void
    {
        $builder = new ContainerBuilder();

        (new SecurityServiceProvider())->register($builder);

        $taggedIds = array_keys($builder->findTaggedServiceIds('security.voter'));

        self::assertContains(CapabilityVoter::class, $taggedIds);
        self::assertContains(RoleVoter::class, $taggedIds);
    }

    #[Test]
    public function skipsUserRepositoryWhenAlreadyRegistered(): void
    {
        $builder = new ContainerBuilder();
        $existing = $builder->register(UserRepository::class);

        (new SecurityServiceProvider())->register($builder);

        // Existing UserRepository definition is reused (not overwritten)
        self::assertSame($existing, $builder->findDefinition(UserRepository::class));
    }

    #[Test]
    public function skipsUserRepositoryWhenInterfaceAlreadyRegistered(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(UserRepositoryInterface::class);

        (new SecurityServiceProvider())->register($builder);

        // Provider sees the interface definition and skips registering UserRepository
        self::assertFalse($builder->hasDefinition(UserRepository::class));
    }
}
