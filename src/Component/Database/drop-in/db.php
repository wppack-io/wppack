<?php

/**
 * WpPack Database Drop-In.
 *
 * This file replaces the default WordPress database layer with a WpPack driver.
 * Copy or symlink this file to wp-content/db.php to activate.
 *
 * Configuration via wp-config.php:
 *
 *   define('WPPACK_DATABASE_DSN', 'sqlite:///path/to/database.db');
 *   define('WPPACK_DATABASE_DSN', 'pgsql://user:pass@host:5432/dbname');
 *   define('WPPACK_DATABASE_DSN', 'mysql://user:pass@host:3306/dbname');
 *   define('WPPACK_DATABASE_DSN', 'wpdb://default');  // use standard wpdb
 *
 * For wpdb:// scheme or when WPPACK_DATABASE_DSN is not defined,
 * this drop-in does nothing and WordPress uses the default wpdb.
 *
 * @package WpPack\Component\Database
 */

declare(strict_types=1);

// Kill switch
if (defined('WPPACK_DATABASE_ENABLED') && !WPPACK_DATABASE_ENABLED) {
    return;
}

// No DSN configured — fall through to default WordPress wpdb
if (!defined('WPPACK_DATABASE_DSN') || WPPACK_DATABASE_DSN === '') {
    return;
}

$wppackDatabaseDsn = WPPACK_DATABASE_DSN;

// wpdb:// scheme — let WordPress create $wpdb normally
if (str_starts_with($wppackDatabaseDsn, 'wpdb://')) {
    return;
}

// mysql:// or mariadb:// — let WordPress handle MySQL natively for best compatibility
if (str_starts_with($wppackDatabaseDsn, 'mysql://') || str_starts_with($wppackDatabaseDsn, 'mariadb://')) {
    return;
}

// Load Composer autoloader
(static function (): void {
    $candidates = [
        ABSPATH . 'vendor/autoload.php',
        \dirname(ABSPATH) . '/vendor/autoload.php',
        ABSPATH . '../vendor/autoload.php',
    ];

    if (\defined('WPPACK_AUTOLOAD_PATH')) {
        array_unshift($candidates, WPPACK_AUTOLOAD_PATH);
    }

    foreach ($candidates as $autoload) {
        if (file_exists($autoload)) {
            require_once $autoload;

            return;
        }
    }

    // Cannot find autoloader — fall through to default WordPress wpdb
    trigger_error(
        'WpPack Database: Composer autoloader not found. Falling back to default wpdb.',
        \E_USER_WARNING,
    );
})();

// Check if WpPack classes are available
if (!class_exists(\WpPack\Component\Database\Driver\Driver::class)) {
    return;
}

// Create driver from DSN
try {
    $wppackDriver = \WpPack\Component\Database\Driver\Driver::fromDsn($wppackDatabaseDsn);
} catch (\Throwable $e) {
    trigger_error(
        'WpPack Database: Failed to create driver from DSN: ' . $e->getMessage(),
        \E_USER_WARNING,
    );

    return;
}

// Get query translator from driver (each driver knows its own translator)
$wppackTranslator = $wppackDriver->getQueryTranslator();

// Extract database name from DSN path
$wppackDsnParsed = \WpPack\Component\Dsn\Dsn::fromString($wppackDatabaseDsn);
$wppackDbName = ltrim($wppackDsnParsed->getPath() ?? '', '/');

if ($wppackDbName === '' || $wppackDbName === ':memory:') {
    $wppackDbName = 'wordpress';
}

// Create WpPack wpdb replacement
$wpdb = new \WpPack\Component\Database\WpPackWpdb(
    driver: $wppackDriver,
    translator: $wppackTranslator,
    dbname: $wppackDbName,
);

// Clean up temporary variables
unset($wppackDatabaseDsn, $wppackDriver, $wppackTranslator, $wppackDsnParsed, $wppackDbName);
