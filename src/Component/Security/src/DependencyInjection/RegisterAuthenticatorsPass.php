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

use WpPack\Component\DependencyInjection\Compiler\CompilerPassInterface;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\Reference;
use WpPack\Component\Security\Authentication\AuthenticationManager;

final class RegisterAuthenticatorsPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $builder): void
    {
        if (!$builder->hasDefinition(AuthenticationManager::class)) {
            return;
        }

        $managerDefinition = $builder->findDefinition(AuthenticationManager::class);

        $authenticators = $builder->findTaggedServiceIds('security.authenticator');

        foreach ($authenticators as $serviceId => $tags) {
            $managerDefinition->addMethodCall('addAuthenticator', [new Reference($serviceId)]);
        }
    }
}
