<?php

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
        $builder->register(ExtensionMimeTypeGuesser::class)
            ->addTag('mime.mime_type_guesser');

        $builder->register(FileinfoMimeTypeGuesser::class)
            ->addTag('mime.mime_type_guesser');

        $builder->register(WordPressMimeTypeGuesser::class)
            ->addTag('mime.mime_type_guesser');

        $builder->register(MimeTypes::class)
            ->addMethodCall('setDefault', [new Reference(MimeTypes::class)]);

        $builder->setAlias(MimeTypesInterface::class, MimeTypes::class);
        $builder->setAlias(MimeTypeGuesserInterface::class, MimeTypes::class);
    }
}
