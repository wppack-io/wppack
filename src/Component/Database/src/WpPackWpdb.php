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

use WpPack\Component\Database\Driver\DriverInterface;
use WpPack\Component\Database\Translator\QueryTranslatorInterface;

/**
 * WordPress wpdb replacement that delegates to a WpPack DriverInterface.
 *
 * Intercepts all queries through query(), translates MySQL SQL to the target
 * engine's dialect via QueryTranslatorInterface, and executes via the driver.
 *
 * Used by the db.php drop-in for non-MySQL engines (SQLite, PostgreSQL, etc.).
 */
class WpPackWpdb extends \wpdb
{
    private readonly DriverInterface $driver;
    private readonly QueryTranslatorInterface $translator;

    public function __construct(
        DriverInterface $driver,
        QueryTranslatorInterface $translator,
        string $dbname,
    ) {
        $this->driver = $driver;
        $this->translator = $translator;

        $GLOBALS['wpdb'] = $this;

        $this->dbname = $dbname;
        $this->charset = 'utf8mb4';
        $this->collate = 'utf8mb4_unicode_ci';
        $this->ready = true;

        // Initialize table names (requires $table_prefix global)
        if (isset($GLOBALS['table_prefix'])) {
            $this->set_prefix($GLOBALS['table_prefix']);
        }
    }

    /**
     * @param string $query
     *
     * @return int|bool
     */
    public function query($query)
    {
        if (!$this->ready) {
            return false;
        }

        $this->flush();
        $this->func_call = "\$db->query(\"$query\")";
        $this->last_query = $query;

        $translated = $this->translator->translate($query);

        if ($translated === []) {
            // Query silently ignored (SET NAMES, LOCK TABLES, etc.)
            $this->last_result = [];
            $this->num_rows = 0;

            return true;
        }

        $lastResult = new Result([]);

        foreach ($translated as $sql) {
            try {
                $lastResult = $this->driver->executeQuery($sql);
            } catch (\Throwable $e) {
                $this->last_error = $e->getMessage();
                $this->last_result = [];
                $this->num_rows = 0;

                return false;
            }
        }

        $rows = $lastResult->fetchAllAssociative();

        $this->last_result = array_map(static fn (array $row) => (object) $row, $rows);
        $this->num_rows = \count($rows);
        $this->rows_affected = $lastResult->rowCount();
        $this->insert_id = $this->driver->lastInsertId();
        $this->last_error = '';

        return $this->rows_affected > 0 ? $this->rows_affected : \count($rows);
    }

    /**
     * @param bool $allow_bail
     */
    public function db_connect($allow_bail = true): bool
    {
        $this->driver->connect();
        $this->dbh = $this->driver->getNativeConnection();
        $this->ready = true;

        return true;
    }

    /**
     * @param \mysqli|null $dbh
     * @param string|null  $charset
     * @param string|null  $collate
     */
    public function set_charset($dbh, $charset = null, $collate = null): void
    {
        // Non-MySQL engines don't use MySQL charset commands
    }

    /**
     * @param list<string> $modes
     */
    public function set_sql_mode($modes = []): void
    {
        // Non-MySQL engines don't have SQL modes
    }

    /**
     * @param string       $db
     * @param \mysqli|null $dbh
     */
    public function select($db, $dbh = null): void
    {
        $this->ready = true;
    }

    /**
     * @param string $data
     *
     * @return string
     */
    public function _real_escape($data): string
    {
        return addslashes($data);
    }

    public function close(): bool
    {
        $this->driver->close();
        $this->ready = false;

        return true;
    }

    /**
     * @param bool $allow_bail
     */
    public function check_connection($allow_bail = true): bool
    {
        return $this->ready;
    }

    /**
     * @param string $db_cap
     * @param string $table_name
     */
    public function has_cap($db_cap, $table_name = ''): bool
    {
        return match ($db_cap) {
            'collation', 'group_concat', 'subqueries' => true,
            'set_charset' => false,
            'utf8mb4' => true,
            'utf8mb4_520' => true,
            default => false,
        };
    }

    public function db_server_info(): string
    {
        return $this->driver->getPlatform()->getEngine()->value;
    }

    public function db_version(): string
    {
        return '8.0.0';
    }
}
