<?php

/**
 * Debug_Bar compatibility stub.
 *
 * Provides a minimal implementation of the Debug Bar main class so that
 * third-party Debug Bar extension plugins can reference it without a fatal
 * error when the original Debug Bar plugin is not installed.
 *
 * @see https://wordpress.org/plugins/debug-bar/
 */

if (class_exists('Debug_Bar')) {
    return;
}

// phpcs:disable PSR1.Classes.ClassDeclaration, Squiz.Classes.ValidClassName
class Debug_Bar
{
    /** @var array */
    public $panels = [];

    public function __construct()
    {
    }

    /**
     * Enqueue scripts and styles (no-op stub).
     *
     * @return void
     */
    public function enqueue()
    {
    }

    /**
     * Initialise panels via the debug_bar_panels filter.
     *
     * @return void
     */
    public function init_panels()
    {
        if (function_exists('apply_filters')) {
            $this->panels = apply_filters('debug_bar_panels', []);
        }
    }
}
