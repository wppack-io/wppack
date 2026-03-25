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
    /** @var array<int, Debug_Bar_Panel> */
    public $panels = [];

    public function __construct() {}

    /**
     * Enqueue scripts and styles (no-op stub).
     *
     * @return void
     */
    public function enqueue() {}

    /**
     * Initialise panels via the debug_bar_panels filter.
     *
     * @return void
     */
    public function init_panels()
    {
        $this->panels = apply_filters('debug_bar_panels', []);
    }
}
