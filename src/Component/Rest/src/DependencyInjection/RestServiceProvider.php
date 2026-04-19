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

namespace WPPack\Component\Rest\DependencyInjection;

use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\DependencyInjection\Reference;
use WPPack\Component\DependencyInjection\ServiceProviderInterface;
use WPPack\Component\HttpFoundation\Request;
use WPPack\Component\Rest\RestRegistry;
use WPPack\Component\Rest\RestUrlGenerator;
use WPPack\Component\Role\Authorization\IsGrantedChecker;
use WPPack\Component\Security\Security;

final class RestServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $definition = $builder->register(RestRegistry::class)
            ->addArgument(new Reference(Request::class));

        if ($builder->hasDefinition(Security::class)) {
            $definition->addArgument(new Reference(Security::class));

            if (!$builder->hasDefinition(IsGrantedChecker::class)) {
                $builder->register(IsGrantedChecker::class)
                    ->addArgument(new Reference(Security::class));
            }

            $definition->addArgument(new Reference(IsGrantedChecker::class));
        }

        $builder->register(RestUrlGenerator::class)
            ->addArgument(new Reference(RestRegistry::class));
    }
}
