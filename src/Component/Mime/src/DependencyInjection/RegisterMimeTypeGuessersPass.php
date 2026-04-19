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

namespace WPPack\Component\Mime\DependencyInjection;

use WPPack\Component\DependencyInjection\Compiler\CompilerPassInterface;
use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\DependencyInjection\Reference;
use WPPack\Component\Mime\MimeTypes;

final class RegisterMimeTypeGuessersPass implements CompilerPassInterface
{
    public function __construct(
        private readonly string $tag = 'mime.mime_type_guesser',
    ) {}

    public function process(ContainerBuilder $builder): void
    {
        if (!$builder->hasDefinition(MimeTypes::class)) {
            return;
        }

        $definition = $builder->findDefinition(MimeTypes::class);

        foreach ($builder->findTaggedServiceIds($this->tag) as $id => $tags) {
            $definition->addMethodCall('registerGuesser', [new Reference($id)]);
        }
    }
}
