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
