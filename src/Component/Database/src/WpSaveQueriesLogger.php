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

namespace WpPack\Component\Database;

/**
 * QueryLogger implementation that writes to WordPress `$wpdb->queries`
 * when the `SAVEQUERIES` constant is enabled.
 *
 * Enables tools such as Query Monitor / Debug Bar to capture queries
 * executed through the WpPack Driver abstraction.
 */
final class WpSaveQueriesLogger implements QueryLoggerInterface
{
    public function __construct(private readonly \wpdb $wpdb) {}

    public function log(string $sql, array $params, float $elapsedMs): void
    {
        if (!\defined('SAVEQUERIES') || !\SAVEQUERIES) {
            return;
        }

        // wpdb::$queries format: [sql, elapsed_seconds, caller]
        $this->wpdb->queries[] = [$sql, $elapsedMs / 1000, ''];
    }
}
