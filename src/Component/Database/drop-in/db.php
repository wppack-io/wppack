<?php

/**
 * WpPack Database Drop-In.
 *
 * Replaces the default WordPress database layer with WpPack drivers.
 * All queries use true prepared statements via DriverInterface.
 * No MySQL connection is created unless the DSN explicitly specifies MySQL.
 *
 * Copy or symlink this file to wp-content/db.php to activate.
 *
 * Configuration via wp-config.php:
 *
 *   define('WPPACK_DATABASE_DSN', 'mysql://user:pass@host:3306/dbname');
 *   define('WPPACK_DATABASE_DSN', 'sqlite:///path/to/database.db');
 *   define('WPPACK_DATABASE_DSN', 'pgsql://user:pass@host:5432/dbname');
 *   define('WPPACK_DATABASE_DSN', 'wpdb://default');  // use standard wpdb
 *
 * Optional reader (read/write split):
 *
 *   define('WPPACK_DATABASE_READER_DSN', 'mysql://user:pass@reader-host:3306/dbname');
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

    trigger_error(
        'WpPack Database: Composer autoloader not found. Falling back to default wpdb.',
        \E_USER_WARNING,
    );
})();

// Check if WpPack classes are available
if (!class_exists(\WpPack\Component\Database\Driver\Driver::class)) {
    return;
}

// Create writer driver from DSN
try {
    $wppackWriter = \WpPack\Component\Database\Driver\Driver::fromDsn($wppackDatabaseDsn);
} catch (\Throwable $e) {
    trigger_error(
        'WpPack Database: Failed to create driver from DSN: ' . $e->getMessage(),
        \E_USER_WARNING,
    );

    return;
}

// Create optional reader driver for read/write split
$wppackReader = null;

if (defined('WPPACK_DATABASE_READER_DSN') && WPPACK_DATABASE_READER_DSN !== '') {
    try {
        $wppackReader = \WpPack\Component\Database\Driver\Driver::fromDsn(WPPACK_DATABASE_READER_DSN);
    } catch (\Throwable $e) {
        trigger_error(
            'WpPack Database: Failed to create reader driver: ' . $e->getMessage(),
            \E_USER_WARNING,
        );
    }
}

// Get query translator from writer driver
$wppackTranslator = $wppackWriter->getQueryTranslator();

// Extract database name from DSN path
$wppackDsnParsed = \WpPack\Component\Dsn\Dsn::fromString($wppackDatabaseDsn);
$wppackDbName = ltrim($wppackDsnParsed->getPath() ?? '', '/');

if ($wppackDbName === '' || $wppackDbName === ':memory:') {
    $wppackDbName = 'wordpress';
}

// Create WpPack wpdb replacement — this replaces $wpdb globally
$wpdb = new \WpPack\Component\Database\WpPackWpdb(
    writer: $wppackWriter,
    translator: $wppackTranslator,
    dbname: $wppackDbName,
    reader: $wppackReader,
);

// Clean up temporary variables
unset($wppackDatabaseDsn, $wppackWriter, $wppackReader, $wppackTranslator, $wppackDsnParsed, $wppackDbName);
