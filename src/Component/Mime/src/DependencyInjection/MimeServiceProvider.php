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

use WPPack\Component\DependencyInjection\ContainerBuilder;
use WPPack\Component\DependencyInjection\Reference;
use WPPack\Component\DependencyInjection\ServiceProviderInterface;
use WPPack\Component\Mime\ExtensionMimeTypeGuesser;
use WPPack\Component\Mime\FileinfoMimeTypeGuesser;
use WPPack\Component\Mime\MimeTypeGuesserInterface;
use WPPack\Component\Mime\MimeTypes;
use WPPack\Component\Mime\MimeTypesInterface;
use WPPack\Component\Mime\WordPressMimeTypeGuesser;

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
