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
use WpPack\Component\Database\Placeholder\PreparedBank;
use WpPack\Component\Database\Sql\PlaceholderScanner;
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

    /** @var int|null Row count from the most recent SQL_CALC_FOUND_ROWS query */
    private ?int $lastFoundRows = null;

    private PreparedBank $preparedBank;

    /** @var list<mixed> params that were bound to the most recent query() call */
    public array $last_params = [];

    private ?LoggerInterface $logger;

    public function __construct(
        DriverInterface $writer,
        QueryTranslatorInterface $translator,
        string $dbname,
        ?DriverInterface $reader = null,
        ?LoggerInterface $logger = null,
        string $charset = 'utf8mb4',
        string $collate = '',
        ?PreparedBank $preparedBank = null,
    ) {
        // Do NOT call parent::__construct() — it tries to connect to MySQL.
        $this->writer = $writer;
        $this->reader = $reader;
        $this->translator = $translator;
        $this->logger = $logger;
        $this->preparedBank = $preparedBank ?? new PreparedBank();

        $GLOBALS['wpdb'] = $this;

        $this->dbname = $dbname;
        $this->charset = $charset;
        $this->collate = $collate;
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

        // Eagerly open the connection so $this->dbh and $this->is_mysql are
        // ready before any caller runs a query. Standard wpdb's constructor
        // does the same via parent::__construct(); we skip parent to avoid
        // its mysqli-only logic, so replicate the connect step here.
        $this->db_connect();
    }

    /**
     * Convert %s / %d / %f / %i placeholders to positional '?' markers plus a
     * single trailing SQL comment that references a per-prepare PreparedBank
     * entry holding the actual values.
     *
     * The returned SQL is safe to concatenate with other prepare() results
     * (WP_Site_Query, WP_Query, ... all do this). query() recovers each
     * fragment's params from the bank by scanning the SQL for "/*WPP:<id>*\/"
     * markers in order, so values never travel through the SQL text itself.
     *
     * Special cases:
     * - %i is expanded inline via quoteIdentifier() because identifiers cannot
     *   be parameterized.
     * - A placeholder that lands inside a single-quoted string literal (e.g.
     *   `"LIKE '%%%s%%'"`) folds the entire '...' into a single bound '?' —
     *   splicing the value would require engine-specific escape forms and
     *   MySQL would treat a spliced '?' as a literal byte. %i inside a
     *   literal is semantic nonsense but still consumes an arg (the quoted-
     *   identifier form is folded into the composite string) so later
     *   placeholders bind to the right values.
     *
     * Accepts args passed as a single array for WordPress legacy compatibility
     * (`prepare($sql, [$a, $b])` === `prepare($sql, $a, $b)`).
     *
     * @param string $query
     * @param mixed  ...$args
     *
     * @return string
     */
    public function prepare($query, ...$args)
    {
        if (\count($args) === 1 && \is_array($args[0])) {
            $args = $args[0];
        }

        if ($args === []) {
            return $query;
        }

        $boundParams = [];
        $paramIndex = 0;
        $length = \strlen($query);
        $out = '';

        // Literal buffering state. When we enter a '...' literal we stop
        // emitting bytes directly and instead build the literal's *logical*
        // string value here, resolving any %s/%d/%f placeholders the template
        // embedded inside it. On close, if the literal contained at least one
        // placeholder we replace the whole '...' with a single '?' bound to
        // the composite string (MySQL would treat '?' inside a literal as a
        // literal byte, so splicing the value won't work uniformly across
        // engines — literal-wrap is the only engine-neutral form). Placeholder-
        // free literals are re-emitted verbatim (with doubled-quote escape).
        $inLiteral = false;
        $literalContent = '';
        $literalHasPlaceholder = false;

        for ($i = 0; $i < $length; $i++) {
            $c = $query[$i];

            if ($c === "'") {
                if ($inLiteral) {
                    // '' inside a literal is a doubled-quote escape for a
                    // single '. Preserve it as a literal quote character in
                    // the logical value.
                    if (($query[$i + 1] ?? '') === "'") {
                        $literalContent .= "'";
                        $i++;

                        continue;
                    }

                    // Closing the literal.
                    if ($literalHasPlaceholder) {
                        $out .= '?';
                        $boundParams[] = $literalContent;
                    } else {
                        $out .= "'" . str_replace("'", "''", $literalContent) . "'";
                    }

                    $inLiteral = false;
                    $literalContent = '';
                    $literalHasPlaceholder = false;

                    continue;
                }

                // Opening the literal.
                $inLiteral = true;

                continue;
            }

            if ($inLiteral) {
                // Backslash escape inside a literal (MySQL default sql_mode).
                // \' is a single quote, \\ is a backslash; anything else
                // passes the two bytes through.
                if ($c === '\\' && $i + 1 < $length) {
                    $next = $query[$i + 1];
                    $literalContent .= ($next === "'" || $next === '\\') ? $next : $c . $next;
                    $i++;

                    continue;
                }

                // %% inside a literal is a literal % character.
                if ($c === '%' && ($query[$i + 1] ?? '') === '%') {
                    $literalContent .= '%';
                    $i++;

                    continue;
                }

                // %s/%d/%f inside a literal contribute their (type-coerced)
                // value to the logical string. They do NOT emit a marker
                // here; the enclosing literal becomes a single parameterized
                // '?' on close. %i inside a literal is semantically nonsense
                // but still consumes an arg so it doesn't shift later binds;
                // the quoted-identifier form is folded into the literal.
                if ($c === '%' && $i + 1 < $length) {
                    $spec = $query[$i + 1];

                    if ($spec === 's' || $spec === 'd' || $spec === 'f' || $spec === 'i') {
                        $value = $args[$paramIndex++] ?? null;
                        $literalContent .= match ($spec) {
                            'd' => (string) (int) $value,
                            'f' => (string) (float) $value,
                            'i' => $this->writer->getPlatform()->quoteIdentifier((string) $value),
                            default => (string) $value,
                        };
                        $literalHasPlaceholder = true;
                        $i++;

                        continue;
                    }
                }

                $literalContent .= $c;

                continue;
            }

            // Outside any literal.

            if ($c === '%' && ($query[$i + 1] ?? '') === '%') {
                $out .= '%';
                $i++;

                continue;
            }

            if ($c === '%' && $i + 1 < $length) {
                $spec = $query[$i + 1];

                if ($spec === 'i' || $spec === 's' || $spec === 'd' || $spec === 'f') {
                    $value = $args[$paramIndex++] ?? null;

                    if ($spec === 'i') {
                        $out .= $this->writer->getPlatform()->quoteIdentifier((string) $value);
                    } else {
                        $out .= '?';
                        $boundParams[] = match ($spec) {
                            'd' => (int) $value,
                            'f' => (float) $value,
                            default => (string) $value,
                        };
                    }

                    $i++;

                    continue;
                }
            }

            $out .= $c;
        }

        // Malformed template (unterminated literal): emit what we have so the
        // driver reports the syntax error at execute time rather than here.
        if ($inLiteral) {
            $out .= "'" . str_replace("'", "''", $literalContent);
        }

        if ($boundParams === []) {
            return $out;
        }

        $id = $this->preparedBank->idFor($out, $boundParams);
        $this->preparedBank->store($id, $boundParams);

        return $out . $this->preparedBank->markerFor($id);
    }

    /**
     * Drop any prepared-bank entries left behind by prepare() calls that were
     * never followed by a matching query(). Intended for long-running processes
     * (WP-CLI workers) where the wpdb instance is reused across many requests.
     */
    public function resetPreparedBank(): void
    {
        $this->preparedBank->reset();
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

        // Recover any params stashed by prepare() via /*WPP:id*/ markers.
        [$cleanQuery, $params] = $this->preparedBank->consume($query);

        // Standard wpdb exposes a `query` filter allowing plugins to inspect
        // or rewrite the SQL before execution. Pass the clean version (with
        // '?' placeholders, no bank markers) so filter consumers see the SQL
        // that will actually run.
        if (\function_exists('apply_filters')) {
            /** @var string|false $filtered */
            $filtered = apply_filters('query', $cleanQuery);

            if ($filtered === '' || $filtered === false) {
                $this->insert_id = 0;

                return false;
            }

            $cleanQuery = (string) $filtered;
        }

        $this->last_query = $cleanQuery;
        $this->last_params = $params;

        // Intercept SELECT FOUND_ROWS() — return stored count from last SQL_CALC_FOUND_ROWS query
        if (preg_match('/^\s*SELECT\s+FOUND_ROWS\s*\(\s*\)/i', $cleanQuery)) {
            $count = $this->lastFoundRows ?? 0;
            $this->last_result = [(object) ['FOUND_ROWS()' => $count]];
            $this->num_rows = 1;
            $this->last_error = '';

            return 1;
        }

        return $this->executeWithDriver($cleanQuery, $params);
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
        $data = $this->validateAndFlatten($table, $data, $format);

        if ($data === false) {
            return false;
        }

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
        $data = $this->validateAndFlatten($table, $data, $format);

        if ($data === false) {
            return false;
        }

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
        $data = $this->validateAndFlatten($table, $data, $format);

        if ($data === false) {
            return false;
        }

        $where = $this->validateAndFlatten($table, $where, $where_format);

        if ($where === false) {
            return false;
        }

        $platform = $this->writer->getPlatform();
        $quotedTable = $platform->quoteIdentifier($table);

        $setClauses = [];
        $params = [];

        foreach ($data as $column => $value) {
            $setClauses[] = $platform->quoteIdentifier($column) . ' = ?';
            $params[] = $value;
        }

        [$whereClauses, $params] = $this->buildWhereClauses($where, $platform, $params);

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
        $where = $this->validateAndFlatten($table, $where, $where_format);

        if ($where === false) {
            return false;
        }

        $platform = $this->writer->getPlatform();
        $quotedTable = $platform->quoteIdentifier($table);

        [$whereClauses, $params] = $this->buildWhereClauses($where, $platform);

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
        $this->is_mysql = $this->engineIs('mysql', 'mariadb');
        $this->ready = true;

        return true;
    }

    /**
     * True when the writer's platform engine matches one of $engines.
     * Single source of truth for engine branching across this class.
     */
    private function engineIs(string ...$engines): bool
    {
        return \in_array($this->writer->getPlatform()->getEngine(), $engines, true);
    }

    public function getWriter(): DriverInterface
    {
        return $this->writer;
    }

    public function getTranslator(): QueryTranslatorInterface
    {
        return $this->translator;
    }

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

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
        return $this->writer->getPlatform()->getEngine();
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
        $this->time_start = $start;
        ++$this->num_queries;
        $driver = $this->selectDriver($sql);

        try {
            $translated = $this->translator->translate($sql);
        } catch (\Throwable $e) {
            $this->logger?->error('Query translation failed', $this->buildLogContext($sql, $params, [
                'error' => $e->getMessage(),
            ]));

            // Prefix distinguishes translator failures from driver failures in last_error
            $this->last_error = '[Translation] ' . $e->getMessage();
            $this->last_result = [];
            $this->num_rows = 0;

            return false;
        }

        if ($translated === []) {
            $this->last_result = [];
            $this->num_rows = 0;

            return true;
        }

        $isSelect = $this->isSelectQuery($sql);
        $hasCalcFoundRows = $isSelect && stripos($sql, 'SQL_CALC_FOUND_ROWS') !== false;

        foreach ($translated as $translatedSql) {
            // Auxiliary SQL (e.g., setval, CREATE INDEX) has no placeholders.
            // Only pass params when the translated SQL contains ? placeholders.
            $stmtParams = str_contains($translatedSql, '?') ? $params : [];

            try {
                if ($isSelect) {
                    $result = $driver->executeQuery($translatedSql, $stmtParams);
                    $rows = $result->fetchAllAssociative();

                    $this->last_result = array_map(static fn(array $row) => (object) $row, $rows);
                    $this->num_rows = \count($rows);
                    $this->rows_affected = 0;

                    // SQL_CALC_FOUND_ROWS: count total rows without LIMIT
                    if ($hasCalcFoundRows) {
                        $this->calculateFoundRows($driver, $translatedSql, $stmtParams);
                    }
                } else {
                    $affected = $driver->executeStatement($translatedSql, $stmtParams);
                    $this->last_result = [];
                    $this->num_rows = 0;
                    $this->rows_affected = $affected;
                }
            } catch (\Throwable $e) {
                $this->logger?->error('Query failed', $this->buildLogContext($translatedSql, $params, [
                    'error' => $e->getMessage(),
                ]));

                $this->last_error = $e->getMessage();
                $this->last_result = [];
                $this->num_rows = 0;

                return false;
            }
        }

        $this->insert_id = $driver->lastInsertId();
        $this->last_error = '';

        $elapsed = microtime(true) - $start;

        $this->logger?->debug('Query executed', $this->buildLogContext($sql, $params, [
            'time_ms' => round($elapsed * 1000, 2),
            'driver' => ($driver === $this->reader) ? 'reader' : 'writer',
        ]));

        if (\defined('SAVEQUERIES') && SAVEQUERIES) {
            // Symfony/Doctrine-style logging: keep the parameterized SQL in
            // $q[0] (matches what the driver actually executed) and carry the
            // values in $q[4]['params']. Panels that want a copy-pasteable
            // string can use $q[4]['interpolated_sql'] instead.
            $this->log_query(
                $sql,
                $elapsed,
                $this->get_caller(),
                $start,
                [
                    'params' => $params,
                    'interpolated_sql' => $this->interpolateForDisplay($sql, $params),
                ],
            );
        }

        return $isSelect ? \count($this->last_result) : $this->rows_affected;
    }

    /**
     * Compose a PSR logger context that never embeds parameter values by
     * default. Bound values are replaced with a type-and-length summary
     * (e.g. `#0 => 'string(7)'`), so password hashes, session tokens, PII
     * etc. don't leak through APM / Elastic ingest.
     *
     * Setting the `WPPACK_DB_LOG_VALUES=1` environment variable opts in to
     * including the raw values (and an interpolated SQL string) for local
     * debugging only — do not enable this in production.
     *
     * @param list<mixed>          $params
     * @param array<string, mixed> $extra
     *
     * @return array<string, mixed>
     */
    private function buildLogContext(string $sql, array $params, array $extra = []): array
    {
        $context = [
            'sql' => $sql,
            'params' => $this->paramsSummary($params),
        ];

        if ($this->shouldLogRawValues()) {
            $context['raw_params'] = $params;
            $context['interpolated_sql'] = $this->interpolateForDisplay($sql, $params);
        }

        return $context + $extra;
    }

    /**
     * Reduce a params list to a type-and-length summary that is safe to send
     * to external logs. The summary preserves positional keys (`#0`, `#1`, …)
     * so operators can correlate failures with prepared-statement slots.
     *
     * @param list<mixed>             $params
     *
     * @return array<string, string>
     */
    private function paramsSummary(array $params): array
    {
        $summary = [];

        foreach ($params as $index => $value) {
            $summary['#' . $index] = match (true) {
                $value === null => 'null',
                \is_bool($value) => 'bool',
                \is_int($value) => 'int',
                \is_float($value) => 'float',
                \is_string($value) => 'string(' . \strlen($value) . ')',
                \is_array($value) => 'array(' . \count($value) . ')',
                \is_object($value) => 'object:' . $value::class,
                default => \get_debug_type($value),
            };
        }

        return $summary;
    }

    private function shouldLogRawValues(): bool
    {
        $flag = $_SERVER['WPPACK_DB_LOG_VALUES'] ?? $_ENV['WPPACK_DB_LOG_VALUES'] ?? getenv('WPPACK_DB_LOG_VALUES');

        return $flag === '1' || $flag === 'true';
    }

    /**
     * Build a human-readable version of a prepared SQL statement by
     * substituting '?' placeholders (outside string literals) with their
     * bound values. Used only for debug logs / SAVEQUERIES output — the
     * driver still executes the original parameterized query.
     *
     * @param list<mixed> $params
     */
    private function interpolateForDisplay(string $sql, array $params): string
    {
        if ($params === [] || !str_contains($sql, '?')) {
            return $sql;
        }

        return PlaceholderScanner::replace(
            $sql,
            function (int $index) use ($params): string {
                if (!\array_key_exists($index, $params)) {
                    return '?';
                }

                $value = $params[$index];

                return match (true) {
                    $value === null => 'NULL',
                    \is_bool($value) => $value ? '1' : '0',
                    \is_int($value), \is_float($value) => (string) $value,
                    default => $this->writer->quoteStringLiteral((string) $value),
                };
            },
        );
    }

    /**
     * Override the wpdb caller-stack summary so Debug Bar's "Queries by
     * Caller" panel shows the real plugin/theme caller instead of our
     * internal WpPackWpdb frames.
     *
     * Standard wpdb::get_caller() filters out its own class, but because
     * WpPackWpdb is a subclass every frame from this class slips past
     * that filter. We fall back to debug_backtrace() and skip any frame
     * belonging to wpdb or a subclass.
     */
    public function get_caller()
    {
        $trace = debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS);
        $callers = [];

        foreach ($trace as $frame) {
            $class = $frame['class'] ?? '';

            if ($class === \wpdb::class || $class === self::class || is_subclass_of($class, \wpdb::class)) {
                continue;
            }

            if ($class !== '') {
                $callers[] = "{$class}{$frame['type']}{$frame['function']}";
            } else {
                $callers[] = $frame['function'];
            }
        }

        return implode(', ', array_reverse($callers));
    }

    /**
     * Execute a COUNT(*) query to determine total rows for SQL_CALC_FOUND_ROWS.
     *
     * Strips LIMIT/OFFSET from the translated SQL and wraps in COUNT(*).
     * The regex matches the outermost LIMIT clause (anchored to end of string
     * with $). Subquery LIMIT clauses are not affected because they are not
     * at the end of the full SQL.
     *
     * @param list<mixed> $params
     */
    private function calculateFoundRows(DriverInterface $driver, string $translatedSql, array $params): void
    {
        try {
            // Strip trailing LIMIT/OFFSET
            $countSql = (string) preg_replace(
                '/\bLIMIT\s+\S+(\s+OFFSET\s+\S+)?\s*$/i',
                '',
                $translatedSql,
            );
            // Strip SQL_CALC_FOUND_ROWS — it is invalid inside a subquery and
            // the enclosing COUNT(*) replaces it
            $countSql = (string) preg_replace('/\bSQL_CALC_FOUND_ROWS\b\s*/i', '', $countSql);

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
     * Build WHERE clauses with NULL-safe IS NULL handling.
     *
     * @param array<string, mixed> $where
     * @param list<mixed> $params Existing params to append to
     *
     * @return array{list<string>, list<mixed>}
     */
    private function buildWhereClauses(array $where, Platform\PlatformInterface $platform, array $params = []): array
    {
        $clauses = [];

        foreach ($where as $column => $value) {
            if ($value === null) {
                $clauses[] = $platform->quoteIdentifier($column) . ' IS NULL';
            } else {
                $clauses[] = $platform->quoteIdentifier($column) . ' = ?';
                $params[] = $value;
            }
        }

        return [$clauses, $params];
    }

    /**
     * @param array<string, mixed>      $data
     * @param array<string>|string|null $format
     *
     * @return int|false
     */
    /**
     * Run wpdb's field validation (format / charset / length) when the
     * underlying connection is mysqli — the exact pre-flight checks
     * wpdb::_insert_replace_helper() would normally apply — and flatten the
     * resulting tagged structure back to [column => value] for our own
     * executeInsertReplace() / update() paths.
     *
     * Returning false mirrors the standard wpdb behavior: the caller must
     * abort the statement so wp_insert_post() can report a WP_Error.
     *
     * For non-mysqli drivers (SQLite, PostgreSQL, Aurora Data API) the
     * DESCRIBE / SHOW FULL COLUMNS queries that feed process_fields are not
     * available, so we skip the validation and trust the driver to surface
     * an error at execute time.
     *
     * @param array<string, mixed>      $data
     * @param array<string>|string|null $format
     *
     * @return array<string, mixed>|false
     */
    private function validateAndFlatten(string $table, array $data, array|string|null $format): array|false
    {
        // process_fields() runs DESCRIBE / SHOW FULL COLUMNS queries, which
        // only make sense on MySQL/MariaDB. Skip the pre-flight entirely for
        // other engines and let execute-time errors surface through the
        // Driver.
        if (!$this->engineIs('mysql', 'mariadb')) {
            return $data;
        }

        $processed = $this->process_fields($table, $data, $format);

        if ($processed === false) {
            return false;
        }

        $flat = [];

        foreach ($processed as $column => $info) {
            $flat[$column] = \is_array($info) && \array_key_exists('value', $info) ? $info['value'] : $info;
        }

        return $flat;
    }

    /**
     * @param array<string, mixed>      $data
     * @param array<string>|string|null $format
     */
    private function executeInsertReplace(string $table, array $data, array|string|null $format, string $verb): int|false
    {
        if ($data === []) {
            return false;
        }

        $platform = $this->writer->getPlatform();
        $quotedTable = $platform->quoteIdentifier($table);

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
