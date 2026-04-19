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

namespace WPPack\Component\Mailer\DependencyInjection;

use WPPack\Component\DependencyInjection\Compiler\CompilerPassInterface;
use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\DependencyInjection\Reference;
use WPPack\Component\Mailer\Transport\Transport;

final class RegisterTransportFactoriesPass implements CompilerPassInterface
{
    public function __construct(
        private readonly string $tag = 'mailer.transport_factory',
    ) {}

    public function process(ContainerBuilder $builder): void
    {
        if (!$builder->hasDefinition(Transport::class)) {
            return;
        }

        $factories = [];
        foreach ($builder->findTaggedServiceIds($this->tag) as $id => $tags) {
            $factories[] = new Reference($id);
        }

        $builder->findDefinition(Transport::class)->setArgument(0, $factories);
    }
}
