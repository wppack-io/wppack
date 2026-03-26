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

namespace WpPack\Component\Security\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Reference;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\Security\Authentication\AuthenticationManager;
use WpPack\Component\Security\AuthenticationSession;
use WpPack\Component\Security\Authorization\Voter\AccessDecisionManager;
use WpPack\Component\Security\DependencyInjection\RegisterAuthenticatorsPass;
use WpPack\Component\Security\DependencyInjection\RegisterVotersPass;
use WpPack\Component\Security\DependencyInjection\SecurityServiceProvider;
use WpPack\Component\Security\Security;

#[CoversClass(SecurityServiceProvider::class)]
#[CoversClass(RegisterAuthenticatorsPass::class)]
#[CoversClass(RegisterVotersPass::class)]
final class SecurityDependencyInjectionTest extends TestCase
{
    // ---------------------------------------------------------------
    // SecurityServiceProvider
    // ---------------------------------------------------------------

    #[Test]
    public function registerRegistersAllServices(): void
    {
        $builder = new ContainerBuilder();
        $provider = new SecurityServiceProvider();
        $provider->register($builder);

        self::assertTrue($builder->hasDefinition(AuthenticationSession::class));
        self::assertTrue($builder->hasDefinition(AuthenticationManager::class));
        self::assertTrue($builder->hasDefinition(AccessDecisionManager::class));
        self::assertTrue($builder->hasDefinition(Security::class));

        // AuthenticationManager receives Request as second argument and AuthenticationSession as third
        $symfonyDef = $builder->getSymfonyBuilder()->findDefinition(AuthenticationManager::class);
        $args = $symfonyDef->getArguments();
        self::assertArrayHasKey(1, $args);
        self::assertInstanceOf(Reference::class, $args[1]);
        self::assertSame(Request::class, (string) $args[1]);
        self::assertArrayHasKey(2, $args);
        self::assertInstanceOf(Reference::class, $args[2]);
        self::assertSame(AuthenticationSession::class, (string) $args[2]);
    }

    // ---------------------------------------------------------------
    // RegisterAuthenticatorsPass
    // ---------------------------------------------------------------

    #[Test]
    public function registerAuthenticatorsPassSkipsWhenNoManager(): void
    {
        $builder = new ContainerBuilder();
        $pass = new RegisterAuthenticatorsPass();

        // Should not throw when AuthenticationManager is not registered
        $pass->process($builder);

        self::assertFalse($builder->hasDefinition(AuthenticationManager::class));
    }

    #[Test]
    public function registerAuthenticatorsPassAddsTaggedAuthenticators(): void
    {
        $builder = new ContainerBuilder();
        $provider = new SecurityServiceProvider();
        $provider->register($builder);

        // Register a tagged authenticator
        $builder->register('test.authenticator')
            ->addTag('security.authenticator');

        $pass = new RegisterAuthenticatorsPass();
        $pass->process($builder);

        // Verify through the Symfony builder that method calls were added
        $symfonyDef = $builder->getSymfonyBuilder()->findDefinition(AuthenticationManager::class);
        $calls = $symfonyDef->getMethodCalls();

        $addAuthenticatorCalls = array_filter($calls, fn($call) => $call[0] === 'addAuthenticator');

        self::assertNotEmpty($addAuthenticatorCalls);
    }

    // ---------------------------------------------------------------
    // RegisterVotersPass
    // ---------------------------------------------------------------

    #[Test]
    public function registerVotersPassSkipsWhenNoManager(): void
    {
        $builder = new ContainerBuilder();
        $pass = new RegisterVotersPass();

        // Should not throw when AccessDecisionManager is not registered
        $pass->process($builder);

        self::assertFalse($builder->hasDefinition(AccessDecisionManager::class));
    }

    #[Test]
    public function registerVotersPassAddsTaggedVoters(): void
    {
        $builder = new ContainerBuilder();
        $provider = new SecurityServiceProvider();
        $provider->register($builder);

        $pass = new RegisterVotersPass();
        $pass->process($builder);

        // Verify through the Symfony builder that method calls were added
        $symfonyDef = $builder->getSymfonyBuilder()->findDefinition(AccessDecisionManager::class);
        $calls = $symfonyDef->getMethodCalls();

        $addVoterCalls = array_filter($calls, fn($call) => $call[0] === 'addVoter');

        // SecurityServiceProvider registers CapabilityVoter and RoleVoter with 'security.voter' tag
        self::assertCount(2, $addVoterCalls);
    }
}
