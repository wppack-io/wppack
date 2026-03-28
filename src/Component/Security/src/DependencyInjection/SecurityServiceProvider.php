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

namespace WpPack\Component\Security\DependencyInjection;

use Psr\EventDispatcher\EventDispatcherInterface;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\Reference;
use WpPack\Component\DependencyInjection\ServiceProviderInterface;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\Security\Authentication\AuthenticationManager;
use WpPack\Component\Security\Authentication\AuthenticationManagerInterface;
use WpPack\Component\Security\AuthenticationSession;
use WpPack\Component\Security\Authentication\Provider\UserProviderInterface;
use WpPack\Component\Security\Authentication\Provider\WordPressUserProvider;
use WpPack\Component\Security\Authorization\AuthorizationChecker;
use WpPack\Component\Security\Authorization\AuthorizationCheckerInterface;
use WpPack\Component\Security\Authorization\Voter\AccessDecisionManager;
use WpPack\Component\Security\Authorization\Voter\CapabilityVoter;
use WpPack\Component\Security\Authorization\Voter\RoleVoter;
use WpPack\Component\Role\Authorization\IsGrantedChecker;
use WpPack\Component\Security\EventListener\CheckCredentialsListener;
use WpPack\Component\Security\Security;

final class SecurityServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        // User Provider
        $builder->register(WordPressUserProvider::class);
        $builder->setAlias(UserProviderInterface::class, WordPressUserProvider::class);

        // Authentication Session
        $builder->register(AuthenticationSession::class);

        // Authentication Manager
        $builder->register(AuthenticationManager::class)
            ->addArgument(new Reference(EventDispatcherInterface::class))
            ->addArgument(new Reference(Request::class))
            ->addArgument(new Reference(AuthenticationSession::class));
        $builder->setAlias(AuthenticationManagerInterface::class, AuthenticationManager::class);

        // Authorization
        $builder->register(CapabilityVoter::class)
            ->addTag('security.voter');
        $builder->register(RoleVoter::class)
            ->addTag('security.voter');
        $builder->register(AccessDecisionManager::class);
        $builder->register(AuthorizationChecker::class)
            ->addArgument(new Reference(AccessDecisionManager::class))
            ->addArgument(new Reference(AuthenticationManagerInterface::class));
        $builder->setAlias(AuthorizationCheckerInterface::class, AuthorizationChecker::class);

        // Security Facade
        $builder->register(Security::class)
            ->addArgument(new Reference(AuthorizationCheckerInterface::class))
            ->addArgument(new Reference(AuthenticationManagerInterface::class));

        // IsGranted Checker
        $builder->register(IsGrantedChecker::class)
            ->addArgument(new Reference(Security::class));

        // Event Listeners
        $builder->register(CheckCredentialsListener::class);
    }
}
