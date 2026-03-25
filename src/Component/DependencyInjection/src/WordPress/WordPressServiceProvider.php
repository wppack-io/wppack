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

namespace WpPack\Component\DependencyInjection\WordPress;

use WpPack\Component\DependencyInjection\ContainerBuilder;
use WpPack\Component\DependencyInjection\ServiceProviderInterface;

final class WordPressServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerBuilder $builder): void
    {
        // Database
        $builder->register('wpdb', \wpdb::class)
            ->setFactory([self::class, 'getWpdb'])
            ->setPublic(true);

        // Filesystem
        $builder->register('wp_filesystem', \WP_Filesystem_Base::class)
            ->setFactory([self::class, 'getWpFilesystem'])
            ->setPublic(true);
        $builder->setAlias(\WP_Filesystem_Base::class, 'wp_filesystem');

        // Core environment
        $builder->register('wp', \WP::class)
            ->setFactory([self::class, 'getWp'])
            ->setPublic(true);
        $builder->setAlias(\WP::class, 'wp');

        // Rewrite rules
        $builder->register('wp_rewrite', \WP_Rewrite::class)
            ->setFactory([self::class, 'getWpRewrite'])
            ->setPublic(true);
        $builder->setAlias(\WP_Rewrite::class, 'wp_rewrite');

        // Main query (stable reference, never modified by query_posts())
        $builder->register('wp_the_query', \WP_Query::class)
            ->setFactory([self::class, 'getWpTheQuery'])
            ->setPublic(true);

        // Current query (may be replaced by query_posts())
        $builder->register('wp_query', \WP_Query::class)
            ->setFactory([self::class, 'getWpQuery'])
            ->setPublic(true);

        // User roles
        $builder->register('wp_roles', \WP_Roles::class)
            ->setFactory([self::class, 'getWpRoles'])
            ->setPublic(true);
        $builder->setAlias(\WP_Roles::class, 'wp_roles');

        // Locale
        $builder->register('wp_locale', \WP_Locale::class)
            ->setFactory([self::class, 'getWpLocale'])
            ->setPublic(true);
        $builder->setAlias(\WP_Locale::class, 'wp_locale');

        // Locale switcher
        $builder->register('wp_locale_switcher', \WP_Locale_Switcher::class)
            ->setFactory([self::class, 'getWpLocaleSwitcher'])
            ->setPublic(true);
        $builder->setAlias(\WP_Locale_Switcher::class, 'wp_locale_switcher');

        // Object cache
        $builder->register('wp_object_cache', \WP_Object_Cache::class)
            ->setFactory([self::class, 'getWpObjectCache'])
            ->setPublic(true);
        $builder->setAlias(\WP_Object_Cache::class, 'wp_object_cache');

        // oEmbed
        $builder->register('wp_embed', \WP_Embed::class)
            ->setFactory([self::class, 'getWpEmbed'])
            ->setPublic(true);
        $builder->setAlias(\WP_Embed::class, 'wp_embed');

        // Widget factory
        $builder->register('wp_widget_factory', \WP_Widget_Factory::class)
            ->setFactory([self::class, 'getWpWidgetFactory'])
            ->setPublic(true);
        $builder->setAlias(\WP_Widget_Factory::class, 'wp_widget_factory');

        // Textdomain registry
        $builder->register('wp_textdomain_registry', \WP_Textdomain_Registry::class)
            ->setFactory([self::class, 'getWpTextdomainRegistry'])
            ->setPublic(true);
        $builder->setAlias(\WP_Textdomain_Registry::class, 'wp_textdomain_registry');

        // Scripts
        $builder->register('wp_scripts', \WP_Scripts::class)
            ->setFactory([self::class, 'getWpScripts'])
            ->setPublic(true);
        $builder->setAlias(\WP_Scripts::class, 'wp_scripts');

        // Styles
        $builder->register('wp_styles', \WP_Styles::class)
            ->setFactory([self::class, 'getWpStyles'])
            ->setPublic(true);
        $builder->setAlias(\WP_Styles::class, 'wp_styles');

        // Admin bar
        $builder->register('wp_admin_bar', \WP_Admin_Bar::class)
            ->setFactory([self::class, 'getWpAdminBar'])
            ->setPublic(true);
        $builder->setAlias(\WP_Admin_Bar::class, 'wp_admin_bar');

        // Customizer
        $builder->register('wp_customize', \WP_Customize_Manager::class)
            ->setFactory([self::class, 'getWpCustomize'])
            ->setPublic(true);
        $builder->setAlias(\WP_Customize_Manager::class, 'wp_customize');
    }

    public static function getWpdb(): \wpdb
    {
        global $wpdb;

        return $wpdb ?? throw new \RuntimeException('Global $wpdb is not initialized.');
    }

    public static function getWpFilesystem(): \WP_Filesystem_Base
    {
        global $wp_filesystem;

        return $wp_filesystem ?? throw new \RuntimeException('Global $wp_filesystem is not initialized. Call WP_Filesystem() first.');
    }

    public static function getWp(): \WP
    {
        global $wp;

        return $wp ?? throw new \RuntimeException('Global $wp is not initialized.');
    }

    public static function getWpRewrite(): \WP_Rewrite
    {
        global $wp_rewrite;

        return $wp_rewrite ?? throw new \RuntimeException('Global $wp_rewrite is not initialized.');
    }

    public static function getWpTheQuery(): \WP_Query
    {
        global $wp_the_query;

        return $wp_the_query ?? throw new \RuntimeException('Global $wp_the_query is not initialized.');
    }

    public static function getWpQuery(): \WP_Query
    {
        global $wp_query;

        return $wp_query ?? throw new \RuntimeException('Global $wp_query is not initialized.');
    }

    public static function getWpRoles(): \WP_Roles
    {
        global $wp_roles;

        return $wp_roles ?? throw new \RuntimeException('Global $wp_roles is not initialized.');
    }

    public static function getWpLocale(): \WP_Locale
    {
        global $wp_locale;

        return $wp_locale ?? throw new \RuntimeException('Global $wp_locale is not initialized.');
    }

    public static function getWpLocaleSwitcher(): \WP_Locale_Switcher
    {
        global $wp_locale_switcher;

        return $wp_locale_switcher ?? throw new \RuntimeException('Global $wp_locale_switcher is not initialized.');
    }

    public static function getWpObjectCache(): \WP_Object_Cache
    {
        global $wp_object_cache;

        return $wp_object_cache ?? throw new \RuntimeException('Global $wp_object_cache is not initialized.');
    }

    public static function getWpEmbed(): \WP_Embed
    {
        global $wp_embed;

        return $wp_embed ?? throw new \RuntimeException('Global $wp_embed is not initialized.');
    }

    public static function getWpWidgetFactory(): \WP_Widget_Factory
    {
        global $wp_widget_factory;

        return $wp_widget_factory ?? throw new \RuntimeException('Global $wp_widget_factory is not initialized.');
    }

    public static function getWpTextdomainRegistry(): \WP_Textdomain_Registry
    {
        global $wp_textdomain_registry;

        return $wp_textdomain_registry ?? throw new \RuntimeException('Global $wp_textdomain_registry is not initialized.');
    }

    public static function getWpScripts(): \WP_Scripts
    {
        global $wp_scripts;

        return $wp_scripts ?? throw new \RuntimeException('Global $wp_scripts is not initialized.');
    }

    public static function getWpStyles(): \WP_Styles
    {
        global $wp_styles;

        return $wp_styles ?? throw new \RuntimeException('Global $wp_styles is not initialized.');
    }

    public static function getWpAdminBar(): \WP_Admin_Bar
    {
        global $wp_admin_bar;

        return $wp_admin_bar ?? throw new \RuntimeException('Global $wp_admin_bar is not initialized.');
    }

    public static function getWpCustomize(): \WP_Customize_Manager
    {
        global $wp_customize;

        return $wp_customize ?? throw new \RuntimeException('Global $wp_customize is not initialized.');
    }
}
