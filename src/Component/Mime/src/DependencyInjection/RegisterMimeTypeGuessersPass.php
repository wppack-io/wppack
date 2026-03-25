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

namespace WpPack\Component\Mime\DependencyInjection;

use WpPack\Component\DependencyInjection\Compiler\CompilerPassInterface;
use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\Reference;
use WpPack\Component\Mime\MimeTypes;

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
