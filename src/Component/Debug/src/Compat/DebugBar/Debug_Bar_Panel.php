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

/**
 * Debug_Bar_Panel compatibility stub.
 *
 * Provides a minimal implementation of the Debug Bar Panel base class so that
 * third-party Debug Bar extension plugins (Debug Bar Console, Debug Bar
 * Transients, Debug Bar Cron, etc.) can be loaded without a fatal error when
 * the original Debug Bar plugin is not installed.
 *
 * @see https://wordpress.org/plugins/debug-bar/
 */

if (class_exists('Debug_Bar_Panel')) {
    return;
}

// phpcs:disable PSR1.Classes.ClassDeclaration, PSR1.Methods.CamelCapsMethodName, Squiz.Classes.ValidClassName
class Debug_Bar_Panel
{
    /** @var string */
    public $_title = '';

    /** @var bool */
    public $_visible = true;

    /**
     * @param string $title Panel title.
     */
    public function __construct($title = '')
    {
        $this->title($title);

        if (false === $this->init()) {
            return;
        }

        add_filter('debug_bar_classes', [$this, 'debug_bar_classes']);
    }

    /**
     * Initialise the panel. Returning false prevents filter registration.
     *
     * @return void|false
     */
    public function init() {}

    /**
     * Called before render().
     *
     * @return void
     */
    public function prerender() {}

    /**
     * Render the panel contents.
     *
     * @return void
     */
    public function render() {}

    /**
     * Whether this panel should be visible.
     *
     * @return bool
     */
    public function is_visible()
    {
        return $this->_visible;
    }

    /**
     * Set the visibility of this panel.
     *
     * @param bool $visible
     * @return void
     */
    public function set_visible($visible)
    {
        $this->_visible = $visible;
    }

    /**
     * Get or set the panel title.
     *
     * @param string|null $title When non-null, sets the title.
     * @return string The panel title.
     */
    public function title($title = null)
    {
        if (null !== $title) {
            $this->_title = $title;
        }

        return $this->_title;
    }

    /**
     * Filter callback for debug_bar_classes.
     *
     * @param array<class-string> $classes
     * @return array<class-string>
     */
    public function debug_bar_classes($classes)
    {
        return $classes;
    }
}
