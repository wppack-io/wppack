<?php

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

    public static function getWp(): \WP
    {
        /** @var \WP $wp */
        global $wp;

        return $wp;
    }

    public static function getWpRewrite(): \WP_Rewrite
    {
        /** @var \WP_Rewrite $wp_rewrite */
        global $wp_rewrite;

        return $wp_rewrite;
    }

    public static function getWpTheQuery(): \WP_Query
    {
        /** @var \WP_Query $wp_the_query */
        global $wp_the_query;

        return $wp_the_query;
    }

    public static function getWpQuery(): \WP_Query
    {
        /** @var \WP_Query $wp_query */
        global $wp_query;

        return $wp_query;
    }

    public static function getWpRoles(): \WP_Roles
    {
        /** @var \WP_Roles $wp_roles */
        global $wp_roles;

        return $wp_roles;
    }

    public static function getWpLocale(): \WP_Locale
    {
        /** @var \WP_Locale $wp_locale */
        global $wp_locale;

        return $wp_locale;
    }

    public static function getWpLocaleSwitcher(): \WP_Locale_Switcher
    {
        /** @var \WP_Locale_Switcher $wp_locale_switcher */
        global $wp_locale_switcher;

        return $wp_locale_switcher;
    }

    public static function getWpObjectCache(): \WP_Object_Cache
    {
        /** @var \WP_Object_Cache $wp_object_cache */
        global $wp_object_cache;

        return $wp_object_cache;
    }

    public static function getWpEmbed(): \WP_Embed
    {
        /** @var \WP_Embed $wp_embed */
        global $wp_embed;

        return $wp_embed;
    }

    public static function getWpWidgetFactory(): \WP_Widget_Factory
    {
        /** @var \WP_Widget_Factory $wp_widget_factory */
        global $wp_widget_factory;

        return $wp_widget_factory;
    }

    public static function getWpTextdomainRegistry(): \WP_Textdomain_Registry
    {
        /** @var \WP_Textdomain_Registry $wp_textdomain_registry */
        global $wp_textdomain_registry;

        return $wp_textdomain_registry;
    }

    public static function getWpScripts(): \WP_Scripts
    {
        /** @var \WP_Scripts $wp_scripts */
        global $wp_scripts;

        return $wp_scripts;
    }

    public static function getWpStyles(): \WP_Styles
    {
        /** @var \WP_Styles $wp_styles */
        global $wp_styles;

        return $wp_styles;
    }

    public static function getWpAdminBar(): \WP_Admin_Bar
    {
        /** @var \WP_Admin_Bar $wp_admin_bar */
        global $wp_admin_bar;

        return $wp_admin_bar;
    }

    public static function getWpCustomize(): \WP_Customize_Manager
    {
        /** @var \WP_Customize_Manager $wp_customize */
        global $wp_customize;

        return $wp_customize;
    }
}
