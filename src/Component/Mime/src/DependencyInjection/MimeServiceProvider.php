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

use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\Reference;
use WpPack\Component\DependencyInjection\ServiceProviderInterface;
use WpPack\Component\Mime\ExtensionMimeTypeGuesser;
use WpPack\Component\Mime\FileinfoMimeTypeGuesser;
use WpPack\Component\Mime\MimeTypeGuesserInterface;
use WpPack\Component\Mime\MimeTypes;
use WpPack\Component\Mime\MimeTypesInterface;
use WpPack\Component\Mime\WordPressMimeTypeGuesser;

final class MimeServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        // Default guessers are registered by MimeTypes constructor.
        // Only custom guessers need the 'mime.mime_type_guesser' tag.
        $builder->register(ExtensionMimeTypeGuesser::class);
        $builder->register(FileinfoMimeTypeGuesser::class);
        $builder->register(WordPressMimeTypeGuesser::class);

        // Synchronize DI-managed instance with the static singleton (Symfony pattern)
        $builder->register(MimeTypes::class)
            ->addMethodCall('setDefault', [new Reference(MimeTypes::class)]);

        $builder->setAlias(MimeTypesInterface::class, MimeTypes::class);
        $builder->setAlias(MimeTypeGuesserInterface::class, MimeTypes::class);
    }
}
