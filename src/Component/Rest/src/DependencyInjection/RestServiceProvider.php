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

namespace WpPack\Component\Rest\DependencyInjection;

use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\Reference;
use WpPack\Component\DependencyInjection\ServiceProviderInterface;
use WpPack\Component\HttpFoundation\Request;
use WpPack\Component\Rest\RestRegistry;
use WpPack\Component\Rest\RestUrlGenerator;
use WpPack\Component\Security\Security;

final class RestServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $definition = $builder->register(RestRegistry::class)
            ->addArgument(new Reference(Request::class));

        if ($builder->hasDefinition(Security::class)) {
            $definition->addArgument(new Reference(Security::class));
        }

        $builder->register(RestUrlGenerator::class)
            ->addArgument(new Reference(RestRegistry::class));
    }
}
