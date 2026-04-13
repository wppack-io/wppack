<?php

/**
 * WpPack Database Drop-In.
 *
 * Replaces the default WordPress database layer with WpPack drivers.
 * All queries use true prepared statements via DriverInterface.
 *
 * Copy or symlink this file to wp-content/db.php to activate.
 *
 * Configuration via wp-config.php:
 *
 *   define('DATABASE_DSN', 'mysql://user:pass@host:3306/dbname');
 *   define('DATABASE_DSN', 'sqlite:///path/to/database.db');
 *   define('DATABASE_DSN', 'pgsql://user:pass@host:5432/dbname');
 *
 * Optional reader (read/write split):
 *
 *   define('DATABASE_READER_DSN', 'mysql://user:pass@reader-host:3306/dbname');
 *
 * When DATABASE_DSN is not defined, a DSN is auto-built from DB_HOST/DB_USER/
 * DB_PASSWORD/DB_NAME constants if they exist (standard WordPress config).
 *
 * @package WpPack\Component\Database
 */

declare(strict_types=1);

// Kill switch
if (defined('DATABASE_DRIVER_ENABLED') && !DATABASE_DRIVER_ENABLED) {
    return;
}

// Build DSN: explicit DATABASE_DSN or auto-build from WordPress DB_* constants
$wppackDatabaseDsn = '';

if (defined('DATABASE_DSN') && DATABASE_DSN !== '') {
    $wppackDatabaseDsn = DATABASE_DSN;
} elseif (defined('DB_HOST') && defined('DB_NAME')) {
    // Auto-build MySQL DSN from standard WordPress constants
    $wppackDbUser = defined('DB_USER') ? DB_USER : 'root';
    $wppackDbPass = defined('DB_PASSWORD') ? DB_PASSWORD : '';
    $wppackDbHost = DB_HOST;
    $wppackDbPort = '3306';

    // Parse host:port
    if (str_contains($wppackDbHost, ':')) {
        [$wppackDbHost, $wppackDbPort] = explode(':', $wppackDbHost, 2);
    }

    $wppackDatabaseDsn = \sprintf(
        'mysql://%s:%s@%s:%s/%s',
        rawurlencode($wppackDbUser),
        rawurlencode($wppackDbPass),
        $wppackDbHost,
        $wppackDbPort,
        DB_NAME,
    );

    unset($wppackDbUser, $wppackDbPass, $wppackDbHost, $wppackDbPort);
}

if ($wppackDatabaseDsn === '') {
    return;
}

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

if (!class_exists(\WpPack\Component\Database\Driver\Driver::class)) {
    return;
}

// Create writer driver
try {
    $wppackWriter = \WpPack\Component\Database\Driver\Driver::fromDsn($wppackDatabaseDsn);
} catch (\Throwable $e) {
    trigger_error(
        'WpPack Database: Failed to create driver from DSN: ' . $e->getMessage(),
        \E_USER_WARNING,
    );

    return;
}

// Create optional reader driver
$wppackReader = null;

if (defined('DATABASE_READER_DSN') && DATABASE_READER_DSN !== '') {
    try {
        $wppackReader = \WpPack\Component\Database\Driver\Driver::fromDsn(DATABASE_READER_DSN);
    } catch (\Throwable $e) {
        trigger_error(
            'WpPack Database: Failed to create reader driver: ' . $e->getMessage(),
            \E_USER_WARNING,
        );
    }
}

// Extract database name from DSN
$wppackDsnParsed = \WpPack\Component\Dsn\Dsn::fromString($wppackDatabaseDsn);
$wppackDbName = ltrim($wppackDsnParsed->getPath() ?? '', '/');

if ($wppackDbName === '' || $wppackDbName === ':memory:') {
    $wppackDbName = defined('DB_NAME') ? DB_NAME : 'wordpress';
}

// Create WpPack wpdb replacement
$wpdb = new \WpPack\Component\Database\WpPackWpdb(
    writer: $wppackWriter,
    translator: $wppackWriter->getQueryTranslator(),
    dbname: $wppackDbName,
    reader: $wppackReader,
);

unset($wppackDatabaseDsn, $wppackWriter, $wppackReader, $wppackDsnParsed, $wppackDbName);
