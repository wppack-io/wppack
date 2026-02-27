<?php

declare(strict_types=1);

namespace WpPack\Component\DependencyInjection\WordPress;

use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\ServiceProviderInterface;

final class WordPressServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        $builder->register('wpdb', \wpdb::class)
            ->setFactory([self::class, 'getWpdb'])
            ->setPublic(true);

        $builder->setAlias(\wpdb::class, 'wpdb');

        $builder->register('wp_filesystem', \WP_Filesystem_Base::class)
            ->setFactory([self::class, 'getWpFilesystem'])
            ->setPublic(true);

        $builder->setAlias(\WP_Filesystem_Base::class, 'wp_filesystem');
    }

    public static function getWpdb(): \wpdb
    {
        /** @var \wpdb $wpdb */
        global $wpdb;

        return $wpdb;
    }

    public static function getWpFilesystem(): \WP_Filesystem_Base
    {
        /** @var \WP_Filesystem_Base $wp_filesystem */
        global $wp_filesystem;

        return $wp_filesystem;
    }
}
