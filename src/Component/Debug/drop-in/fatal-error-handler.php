<?php

/*
 * This file is part of the WpPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * WpPack Fatal Error Handler Drop-in
 *
 * Copy this file to wp-content/fatal-error-handler.php.
 *
 * Configuration (wp-config.php):
 *   define('WPPACK_DEBUG_ENABLED', false);  // optional, disable drop-in (kill switch)
 *
 * When active, this drop-in provides two layers of error handling:
 *
 * 1. ExceptionHandler — registered via set_exception_handler() at drop-in
 *    load time (before plugins load). Catches uncaught exceptions during plugin
 *    loading, DI container compilation, and Kernel::boot().
 *
 * 2. FatalErrorHandler — returned as the WP_Fatal_Error_Handler implementation.
 *    Catches fatal PHP errors (E_ERROR, E_PARSE, etc.) at shutdown.
 *
 * Once DebugPlugin::boot() runs, the full ExceptionHandler (with DI dependencies)
 * overwrites set_exception_handler(), keeping the early instance as the previous
 * handler.
 *
 * Activation logic:
 *   - WPPACK_DEBUG_ENABLED = false → disabled (kill switch, return null)
 *   - WP_DEBUG = false             → disabled (return null)
 *   - WP_DEBUG = true (default)    → enabled
 *
 * @package wppack/debug
 */

declare(strict_types=1);

use WpPack\Component\Debug\DataCollector\WpErrorDataCollector;
use WpPack\Component\Debug\ErrorHandler\ErrorRenderer;
use WpPack\Component\Debug\ErrorHandler\ExceptionHandler;
use WpPack\Component\Debug\ErrorHandler\FatalErrorHandler;
use WpPack\Component\Debug\ErrorHandler\RedirectHandler;

// Kill switch: define('WPPACK_DEBUG_ENABLED', false) to disable
if (\defined('WPPACK_DEBUG_ENABLED') && !WPPACK_DEBUG_ENABLED) {
    return null;
}

// Require WP_DEBUG to be enabled
if (!\defined('WP_DEBUG') || !WP_DEBUG) {
    return null;
}

// Locate and load Composer autoloader.
// Wrapped in an IIFE to avoid leaking variables into the global scope.
(static function (): void {
    $candidates = [
        // Plugin standalone install
        WP_CONTENT_DIR . '/plugins/wppack-debug/vendor/autoload.php',
        WP_CONTENT_DIR . '/mu-plugins/wppack-debug/vendor/autoload.php',
        // Standard WordPress layout
        ABSPATH . 'vendor/autoload.php',
        // Bedrock / project root
        \dirname(ABSPATH) . '/vendor/autoload.php',
    ];

    foreach ($candidates as $autoload) {
        if (file_exists($autoload)) {
            require_once $autoload;

            return;
        }
    }
})();

if (!class_exists(FatalErrorHandler::class)) {
    return null;
}

$renderer = new ErrorRenderer();

$exceptionHandler = new ExceptionHandler($renderer);
$exceptionHandler->register();

// Register WP_Error data collector early so that all wp_error_added events
// during the request are captured, including those before the DI container boots.
// Also provides origin capture (WeakMap) for WpDieHandler.
$redirectHandler = new RedirectHandler($renderer);
$redirectHandler->register();
$GLOBALS['_wppack_redirect_handler'] = $redirectHandler;

$wpErrorCollector = new WpErrorDataCollector();
$wpErrorCollector->register();
$GLOBALS['_wppack_wp_error_collector'] = $wpErrorCollector;

return new FatalErrorHandler($renderer);
