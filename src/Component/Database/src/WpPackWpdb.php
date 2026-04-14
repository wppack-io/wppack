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

use Psr\Log\LoggerInterface;
use WpPack\Component\Database\Driver\DriverInterface;
use WpPack\Component\Database\Translator\QueryTranslatorInterface;

/**
 * WordPress wpdb replacement with true prepared statements and reader/writer support.
 *
 * Overrides prepare() to keep parameters separate from SQL, then query() passes
 * them to the Driver for native prepared statement execution. No MySQL connection
 * is ever created — all queries go through the WpPack Driver abstraction.
 *
 * Used by the db.php drop-in. Supports all database engines via DriverInterface.
 */
class WpPackWpdb extends \wpdb
{
    private readonly DriverInterface $writer;
    private readonly ?DriverInterface $reader;
    private readonly QueryTranslatorInterface $translator;

    /** @var list<mixed>|null Parameters from the most recent prepare() call */
    private ?array $preparedParams = null;

    /** @var string|null SQL from the most recent prepare() call (for query() verification) */
    private ?string $preparedSql = null;

    /** @var int|null Row count from the most recent SQL_CALC_FOUND_ROWS query */
    private ?int $lastFoundRows = null;

    private ?LoggerInterface $logger;

    public function __construct(
        DriverInterface $writer,
        QueryTranslatorInterface $translator,
        string $dbname,
        ?DriverInterface $reader = null,
        ?LoggerInterface $logger = null,
    ) {
        // Do NOT call parent::__construct() — it tries to connect to MySQL.
        $this->writer = $writer;
        $this->reader = $reader;
        $this->translator = $translator;
        $this->logger = $logger;

        $GLOBALS['wpdb'] = $this;

        $this->dbname = $dbname;
        $this->charset = 'utf8mb4';
        $this->collate = 'utf8mb4_unicode_ci';
        $this->ready = true;

        // Initialize properties that parent::__construct() would set
        $this->last_result = [];
        $this->last_error = '';
        $this->last_query = '';
        $this->insert_id = 0;
        $this->num_rows = 0;
        $this->rows_affected = 0;
        $this->func_call = '';
        $this->queries = [];

        if (isset($GLOBALS['table_prefix'])) {
            $this->set_prefix($GLOBALS['table_prefix']);
        }
    }

    /**
     * Override prepare() to keep parameters separate instead of interpolating.
     *
     * Converts %s/%d/%f to ? placeholders and stores parameters in $preparedParams.
     * The returned SQL string contains ? placeholders (not interpolated values).
     * query() will detect matching SQL and pass stored params to the driver.
     *
     * %i (identifier) placeholders are expanded inline via quoteIdentifier()
     * since they cannot be parameterized.
     *
     * @param string $query
     * @param mixed  ...$args
     *
     * @return string SQL with ? placeholders
     */
    public function prepare($query, ...$args)
    {
        // WordPress may pass args as a single array (legacy support)
        if (\count($args) === 1 && \is_array($args[0])) {
            $args = $args[0];
        }

        if ($args === []) {
            return $query;
        }

        $nativeParams = [];
        $paramIndex = 0;

        // Temporarily escape literal %%
        $sql = str_replace('%%', "\x00WPPACK_PERCENT\x00", $query);

        $sql = (string) preg_replace_callback('/%([sdfi])/', function (array $m) use ($args, &$nativeParams, &$paramIndex): string {
            $value = $args[$paramIndex++] ?? null;

            if ($m[1] === 'i') {
                // %i (identifier) cannot be a prepared statement parameter
                return $this->writer->getPlatform()->quoteIdentifier((string) $value);
            }

            $nativeParams[] = match ($m[1]) {
                'd' => (int) $value,
                'f' => (float) $value,
                default => (string) $value,
            };

            return '?';
        }, $sql);

        // Restore literal percent
        $sql = str_replace("\x00WPPACK_PERCENT\x00", '%', $sql);

        $this->preparedParams = $nativeParams;
        $this->preparedSql = $sql;

        return $sql;
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

        // Use prepared params only if SQL matches (prevents stale param reuse)
        $params = [];

        if ($this->preparedParams !== null && $query === $this->preparedSql) {
            $params = $this->preparedParams;
        }

        $this->preparedParams = null;
        $this->preparedSql = null;

        // Intercept SELECT FOUND_ROWS() — return stored count from last SQL_CALC_FOUND_ROWS query
        if (preg_match('/^\s*SELECT\s+FOUND_ROWS\s*\(\s*\)/i', $query)) {
            $count = $this->lastFoundRows ?? 0;
            $this->last_result = [(object) ['FOUND_ROWS()' => $count]];
            $this->num_rows = 1;
            $this->last_error = '';

            return 1;
        }

        return $this->executeWithDriver($query, $params);
    }

    /**
     * Insert a row. Bypasses prepare()+query() for direct prepared statement.
     *
     * @param string                        $table
     * @param array<string, mixed>          $data
     * @param array<string>|string|null     $format
     *
     * @return int|false
     */
    public function insert($table, $data, $format = null)
    {
        return $this->executeInsertReplace($table, $data, $format, 'INSERT');
    }

    /**
     * Replace a row. Bypasses prepare()+query() for direct prepared statement.
     *
     * @param string                        $table
     * @param array<string, mixed>          $data
     * @param array<string>|string|null     $format
     *
     * @return int|false
     */
    public function replace($table, $data, $format = null)
    {
        return $this->executeInsertReplace($table, $data, $format, 'REPLACE');
    }

    /**
     * Update rows. Bypasses prepare()+query() for direct prepared statement.
     *
     * @param string                        $table
     * @param array<string, mixed>          $data
     * @param array<string, mixed>          $where
     * @param array<string>|string|null     $format
     * @param array<string>|string|null     $where_format
     *
     * @return int|false
     */
    public function update($table, $data, $where, $format = null, $where_format = null)
    {

        $platform = $this->writer->getPlatform();
        $quotedTable = $platform->quoteIdentifier($this->prefix . $table);

        $setClauses = [];
        $params = [];

        foreach ($data as $column => $value) {
            $setClauses[] = $platform->quoteIdentifier($column) . ' = ?';
            $params[] = $value;
        }

        $whereClauses = [];

        foreach ($where as $column => $value) {
            $whereClauses[] = $platform->quoteIdentifier($column) . ' = ?';
            $params[] = $value;
        }

        $sql = \sprintf(
            'UPDATE %s SET %s WHERE %s',
            $quotedTable,
            implode(', ', $setClauses),
            implode(' AND ', $whereClauses),
        );

        $result = $this->executeWithDriver($sql, $params);

        return $result === false ? false : (int) $result;
    }

    /**
     * Delete rows. Bypasses prepare()+query() for direct prepared statement.
     *
     * @param string                        $table
     * @param array<string, mixed>          $where
     * @param array<string>|string|null     $where_format
     *
     * @return int|false
     */
    public function delete($table, $where, $where_format = null)
    {

        $platform = $this->writer->getPlatform();
        $quotedTable = $platform->quoteIdentifier($this->prefix . $table);

        $whereClauses = [];
        $params = [];

        foreach ($where as $column => $value) {
            $whereClauses[] = $platform->quoteIdentifier($column) . ' = ?';
            $params[] = $value;
        }

        $sql = \sprintf(
            'DELETE FROM %s WHERE %s',
            $quotedTable,
            implode(' AND ', $whereClauses),
        );

        $result = $this->executeWithDriver($sql, $params);

        return $result === false ? false : (int) $result;
    }

    public function db_connect($allow_bail = true): bool
    {
        $this->writer->connect();
        $this->reader?->connect();
        $this->dbh = $this->writer->getNativeConnection();
        $this->ready = true;

        return true;
    }

    public function getWriter(): DriverInterface
    {
        return $this->writer;
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * @param \mysqli|null $dbh
     * @param string|null  $charset
     * @param string|null  $collate
     */
    /**
     * No-op: charset is set at the driver connection level, not via SQL.
     *
     * - MySQL: mysqli::set_charset() in MysqlDriver::doConnect()
     * - PostgreSQL: client_encoding in PgsqlDriver connection string
     * - SQLite: UTF-8 internally (not configurable)
     * - RDS Data API: controlled by Aurora instance configuration
     *
     * WordPress calls this method after connection, but it is unnecessary
     * because each driver already sets charset during connect().
     *
     * @param \mysqli|null $dbh
     * @param string|null  $charset
     * @param string|null  $collate
     */
    public function set_charset($dbh, $charset = null, $collate = null): void {}

    /**
     * @param list<string> $modes
     */
    public function set_sql_mode($modes = []): void {}

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
        $this->writer->close();
        $this->reader?->close();
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
        return $this->writer->getPlatform()->getEngine()->value;
    }

    public function db_version(): string
    {
        return '8.0.0';
    }

    /**
     * @param list<mixed> $params
     *
     * @return int|bool
     */
    private function executeWithDriver(string $sql, array $params): int|bool
    {
        $start = microtime(true);
        $driver = $this->selectDriver($sql);
        $translated = $this->translator->translate($sql);

        if ($translated === []) {
            $this->last_result = [];
            $this->num_rows = 0;

            return true;
        }

        $isSelect = $this->isSelectQuery($sql);
        $hasCalcFoundRows = $isSelect && stripos($sql, 'SQL_CALC_FOUND_ROWS') !== false;

        foreach ($translated as $translatedSql) {
            try {
                if ($isSelect) {
                    $result = $driver->executeQuery($translatedSql, $params);
                    $rows = $result->fetchAllAssociative();

                    $this->last_result = array_map(static fn(array $row) => (object) $row, $rows);
                    $this->num_rows = \count($rows);
                    $this->rows_affected = 0;

                    // SQL_CALC_FOUND_ROWS: count total rows without LIMIT
                    if ($hasCalcFoundRows) {
                        $this->calculateFoundRows($driver, $translatedSql, $params);
                    }
                } else {
                    $affected = $driver->executeStatement($translatedSql, $params);
                    $this->last_result = [];
                    $this->num_rows = 0;
                    $this->rows_affected = $affected;
                }
            } catch (\Throwable $e) {
                $this->logger?->error('Query failed', [
                    'sql' => $translatedSql,
                    'params' => $params,
                    'error' => $e->getMessage(),
                ]);

                $this->last_error = $e->getMessage();
                $this->last_result = [];
                $this->num_rows = 0;

                return false;
            }
        }

        $this->insert_id = $driver->lastInsertId();
        $this->last_error = '';

        $this->logger?->debug('Query executed', [
            'sql' => $sql,
            'params' => $params,
            'time_ms' => round((microtime(true) - $start) * 1000, 2),
            'driver' => ($driver === $this->reader) ? 'reader' : 'writer',
        ]);

        return $isSelect ? \count($this->last_result) : $this->rows_affected;
    }

    /**
     * Execute a COUNT(*) query to determine total rows for SQL_CALC_FOUND_ROWS.
     *
     * Strips LIMIT/OFFSET from the translated SQL and wraps in COUNT(*).
     */
    /**
     * @param list<mixed> $params
     */
    private function calculateFoundRows(DriverInterface $driver, string $translatedSql, array $params): void
    {
        try {
            $countSql = (string) preg_replace(
                '/\bLIMIT\s+\S+(\s+OFFSET\s+\S+)?\s*$/i',
                '',
                $translatedSql,
            );
            $result = $driver->executeQuery(
                'SELECT COUNT(*) FROM (' . $countSql . ') AS _wppack_found',
                $params,
            );
            $this->lastFoundRows = (int) $result->fetchOne();
        } catch (\Throwable) {
            $this->lastFoundRows = 0;
        }
    }

    private function isSelectQuery(string $sql): bool
    {
        $trimmed = ltrim($sql);

        return (bool) preg_match('/^(SELECT|SHOW|DESCRIBE|EXPLAIN|PRAGMA)\b/i', $trimmed);
    }

    private function selectDriver(string $sql): DriverInterface
    {
        if ($this->reader === null) {
            return $this->writer;
        }

        $trimmed = ltrim($sql);

        if (preg_match('/^(SELECT|SHOW|DESCRIBE|EXPLAIN)\b/i', $trimmed)) {
            return $this->reader;
        }

        return $this->writer;
    }

    /**
     * @param array<string, mixed>      $data
     * @param array<string>|string|null $format
     *
     * @return int|false
     */
    private function executeInsertReplace(string $table, array $data, array|string|null $format, string $verb): int|false
    {
        if ($data === []) {
            return false;
        }

        $platform = $this->writer->getPlatform();
        $quotedTable = $platform->quoteIdentifier($this->prefix . $table);

        $columns = [];
        $placeholders = [];
        $params = [];

        foreach ($data as $column => $value) {
            $columns[] = $platform->quoteIdentifier($column);
            $placeholders[] = '?';
            $params[] = $value;
        }

        $sql = \sprintf(
            '%s INTO %s (%s) VALUES (%s)',
            $verb,
            $quotedTable,
            implode(', ', $columns),
            implode(', ', $placeholders),
        );

        $result = $this->executeWithDriver($sql, $params);

        return $result === false ? false : (int) $result;
    }
}
