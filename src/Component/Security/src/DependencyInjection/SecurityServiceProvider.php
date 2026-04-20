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

namespace WPPack\Component\Security\DependencyInjection;

use Psr\EventDispatcher\EventDispatcherInterface;
use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\DependencyInjection\Reference;
use WPPack\Component\DependencyInjection\ServiceProviderInterface;
use WPPack\Component\HttpFoundation\Request;
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
use WPPack\Component\Security\EventListener\CheckCredentialsListener;
use WPPack\Component\Security\Security;
use WPPack\Component\User\UserRepository;
use WPPack\Component\User\UserRepositoryInterface;

final class SecurityServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        // User Repository (default registration if not already registered by plugin)
        if (!$builder->hasDefinition(UserRepository::class) && !$builder->hasDefinition(UserRepositoryInterface::class)) {
            $builder->register(UserRepository::class);
            $builder->setAlias(UserRepositoryInterface::class, UserRepository::class);
        }

        // User Provider
        $builder->register(WordPressUserProvider::class)
            ->addArgument(new Reference(UserRepositoryInterface::class));
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
