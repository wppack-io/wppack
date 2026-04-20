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
use WPPack\Component\Security\Authentication\AuthenticationManager;
use WPPack\Component\Security\Authorization\Voter\AccessDecisionManager;
use WPPack\Component\Security\DependencyInjection\RegisterAuthenticatorsPass;
use WPPack\Component\Security\DependencyInjection\RegisterVotersPass;

#[CoversClass(RegisterAuthenticatorsPass::class)]
#[CoversClass(RegisterVotersPass::class)]
final class CompilerPassesTest extends TestCase
{
    // ── RegisterVotersPass ──────────────────────────────────────────────

    #[Test]
    public function votersPassNoOpWhenAccessDecisionManagerAbsent(): void
    {
        $builder = new ContainerBuilder();
        $builder->register('my.voter')->addTag('security.voter');

        (new RegisterVotersPass())->process($builder);

        self::assertFalse($builder->hasDefinition(AccessDecisionManager::class));
    }

    #[Test]
    public function votersPassRegistersTaggedVotersAsAddVoterCalls(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(AccessDecisionManager::class);
        $builder->register('capability.voter')->addTag('security.voter');
        $builder->register('role.voter')->addTag('security.voter');

        (new RegisterVotersPass())->process($builder);

        $calls = $builder->findDefinition(AccessDecisionManager::class)->getMethodCalls();
        self::assertCount(2, $calls);
        foreach ($calls as $call) {
            self::assertSame('addVoter', $call['method']);
        }
    }

    #[Test]
    public function votersPassNoCallsWhenNothingTagged(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(AccessDecisionManager::class);

        (new RegisterVotersPass())->process($builder);

        self::assertSame([], $builder->findDefinition(AccessDecisionManager::class)->getMethodCalls());
    }

    // ── RegisterAuthenticatorsPass ──────────────────────────────────────

    #[Test]
    public function authenticatorsPassNoOpWhenAuthenticationManagerAbsent(): void
    {
        $builder = new ContainerBuilder();
        $builder->register('my.auth')->addTag('security.authenticator');

        (new RegisterAuthenticatorsPass())->process($builder);

        self::assertFalse($builder->hasDefinition(AuthenticationManager::class));
    }

    #[Test]
    public function authenticatorsPassRegistersAsAddAuthenticatorCalls(): void
    {
        $builder = new ContainerBuilder();
        $builder->register(AuthenticationManager::class);
        $builder->register('form.auth')->addTag('security.authenticator');
        $builder->register('cookie.auth')->addTag('security.authenticator');
        $builder->register('apppw.auth')->addTag('security.authenticator');

        (new RegisterAuthenticatorsPass())->process($builder);

        $calls = $builder->findDefinition(AuthenticationManager::class)->getMethodCalls();
        self::assertCount(3, $calls);
        foreach ($calls as $call) {
            self::assertSame('addAuthenticator', $call['method']);
        }
    }
}
