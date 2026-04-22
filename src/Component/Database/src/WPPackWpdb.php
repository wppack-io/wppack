<?php

/*
 * This file is part of the WPPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WPPack\Component\Database;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use WPPack\Component\Database\Driver\DriverInterface;
use WPPack\Component\Database\Event\DatabaseQueryCompletedEvent;
use WPPack\Component\Database\Event\DatabaseQueryFailedEvent;
use WPPack\Component\Database\Placeholder\PreparedBank;
use WPPack\Component\Database\Sql\PlaceholderScanner;
use WPPack\Component\Database\Translator\QueryTranslatorInterface;

/**
 * WordPress wpdb replacement with true prepared statements and reader/writer support.
 *
 * Overrides prepare() to keep parameters separate from SQL, then query() passes
 * them to the Driver for native prepared statement execution. No MySQL connection
 * is ever created — all queries go through the WPPack Driver abstraction.
 *
 * Used by the db.php drop-in. Supports all database engines via DriverInterface.
 */
class WPPackWpdb extends \wpdb
{
    private readonly DriverInterface $writer;
    private readonly ?DriverInterface $reader;
    private readonly QueryTranslatorInterface $translator;

    /** @var int|null Row count from the most recent SQL_CALC_FOUND_ROWS query */
    private ?int $lastFoundRows = null;

    /**
     * Once a write (INSERT / UPDATE / DELETE / DDL / BEGIN) happens within a
     * request, every subsequent query is routed to the writer driver, even
     * SELECTs. This implements read-your-own-writes affinity: replication lag
     * between writer and reader replicas could otherwise return stale data
     * moments after we commit to the primary.
     */
    private bool $stickyWriter = false;

    /**
     * Logical transaction nesting depth. BEGIN / START TRANSACTION /
     * SAVEPOINT increment, COMMIT / ROLLBACK / RELEASE SAVEPOINT decrement
     * (clamped to 0). We use this purely for diagnostics (MySQL silently
     * commits the outer transaction on a nested BEGIN — a classic plugin
     * footgun — so we log a warning when that happens). stickyWriter is
     * not affected: read-your-own-writes stays on for the rest of the
     * request regardless of depth.
     */
    private int $transactionDepth = 0;

    private PreparedBank $preparedBank;

    /** @var list<mixed> params that were bound to the most recent query() call */
    public array $last_params = [];

    /**
     * Mirror of the parent wpdb's public `$errno` property. Plugins defending
     * against specific MySQL error codes (e.g. 2006 'server has gone away',
     * 1062 duplicate key) read this to decide whether to retry or surface an
     * error. For non-MySQL engines the driver reports 0 — the underlying
     * exception carries the engine-specific SQLSTATE / error text via
     * last_error instead.
     */
    public int $errno = 0;

    private ?LoggerInterface $logger;
    private ?EventDispatcherInterface $eventDispatcher;

    public function __construct(
        DriverInterface $writer,
        QueryTranslatorInterface $translator,
        string $dbname,
        ?DriverInterface $reader = null,
        ?LoggerInterface $logger = null,
        string $charset = 'utf8mb4',
        string $collate = '',
        ?PreparedBank $preparedBank = null,
        ?EventDispatcherInterface $eventDispatcher = null,
    ) {
        // Do NOT call parent::__construct() — it tries to connect to MySQL.
        $this->writer = $writer;
        $this->reader = $reader;
        $this->translator = $translator;
        $this->logger = $logger;
        $this->eventDispatcher = $eventDispatcher;
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
     * - `null` bound via `%s` is coerced to the empty string `''`, matching
     *   standard wpdb behaviour. To express a real SQL NULL (e.g. `WHERE
     *   col IS NULL`), use the `WPPackWpdb::insert()/update()/delete()`
     *   API — those paths treat PHP null specially and emit `IS NULL`
     *   clauses. Plugin code composing raw SQL through prepare() that needs
     *   NULL semantics must embed the literal `NULL` keyword itself.
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

        // Malformed template (unterminated literal). Soft-emitting the
        // partial literal lets a driver-side syntax error surface eventually,
        // but at the cost of hiding the root cause — by the time the error
        // reaches the operator the stack trace is pointing at execute() not
        // at the offending prepare() callsite. Fail fast with a clear
        // message instead: unterminated SQL literals are always a bug in
        // the caller's template string, never a legitimate runtime path.
        if ($inLiteral) {
            throw new \InvalidArgumentException(
                'WPPackWpdb::prepare(): unterminated single-quoted literal in template. '
                . 'Check that every opening quote has a matching close quote. '
                . '[Query: ' . (mb_strlen($query) > 200 ? mb_substr($query, 0, 200) . '...' : $query) . ']',
            );
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
        //
        // IMPORTANT: filter callbacks may append / modify static SQL fragments
        // (e.g. add a multisite tenant predicate) but must NOT introduce
        // additional '?' placeholders. Params are already extracted by bank
        // consume at this point; any '?' added by the filter would lack a
        // corresponding bound value and the driver would error at execute
        // time. Plugin authors that need new bound values should use
        // prepare() themselves to produce a new (marker, param) pair.
        if (\function_exists('apply_filters')) {
            /** @var string|false $filtered */
            $filtered = apply_filters('query', $cleanQuery);

            if ($filtered === '' || $filtered === false) {
                $this->insert_id = 0;

                return false;
            }

            $cleanQuery = (string) $filtered;
        }

        // last_query holds the MySQL-shaped SQL the caller handed us
        // (with '?' placeholders substituted in where prepare() was used,
        // bank markers already removed, plugin filter applied). This
        // matches the shape wpdb-consuming plugins expect — e.g.
        // Query Monitor parses it as MySQL. The engine-specific
        // translation happens downstream inside executeWithDriver() and
        // is only exposed via the logger 'sql' context + SAVEQUERIES
        // entry; callers that want the translated form must subscribe
        // to DatabaseQueryCompletedEvent instead.
        $this->last_query = $cleanQuery;
        $this->last_params = $params;

        $this->updateTransactionDepth($cleanQuery);

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

        if ($data === null) {
            return false;
        }

        return $this->executeInsertReplace($table, $data, 'INSERT');
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

        if ($data === null) {
            return false;
        }

        return $this->executeInsertReplace($table, $data, 'REPLACE');
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

        if ($data === null) {
            return false;
        }

        $where = $this->validateAndFlatten($table, $where, $where_format);

        if ($where === null) {
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

        if ($where === null) {
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

        // $dbh shape varies by engine: \mysqli on MySQL/MariaDB,
        // \PgSql\Connection on PostgreSQL, \PDO on SQLite, the
        // RdsDataServiceClient on Aurora Data API / DSQL. Legacy plugin
        // code that assumes the mysqli API (e.g. $wpdb->dbh->real_escape_string)
        // will break on non-MySQL engines; callers that need engine-
        // agnostic escaping should use $wpdb->_real_escape() which
        // delegates to Driver::escapeStringContent.
        $this->dbh = $this->writer->getNativeConnection();
        $this->is_mysql = $this->engineIs('mysql', 'mariadb');
        // Note: parent wpdb's `$use_mysqli` is private so a subclass
        // cannot set it. Plugins that read it directly will see the
        // parent's default (false) regardless. Most defensive code uses
        // the public `$is_mysql` instead, which we do keep current.
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

    public function setEventDispatcher(EventDispatcherInterface $dispatcher): void
    {
        $this->eventDispatcher = $dispatcher;
    }

    /**
     * No-op: charset is set at the driver connection level, not via SQL.
     *
     * - MySQL: mysqli::set_charset() in MySQLDriver::doConnect()
     * - PostgreSQL: client_encoding in PostgreSQLDriver connection string
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
     * Escape a string for splicing into SQL. Mirrors wpdb::_real_escape but
     * delegates to the writer driver so PostgreSQL / SQLite callers get the
     * right escape form instead of a MySQL-shaped addslashes() output. The
     * result is also placeholder-escaped (percent signs) so downstream
     * prepare() calls don't accidentally interpret embedded `%` as tokens.
     *
     * New code should use prepare() with %s/%d placeholders — this path is
     * retained for legacy plugin compatibility only.
     *
     * @param mixed $data
     *
     * @return string
     */
    public function _real_escape($data): string
    {
        if (!\is_string($data)) {
            return '';
        }

        return $this->add_placeholder_escape($this->writer->escapeStringContent($data));
    }

    public function close(): bool
    {
        if (!$this->ready) {
            // Already closed — avoid double-close which some drivers (RDS
            // Data API client, PDO under certain configs) throw on.
            return true;
        }

        $this->writer->close();
        $this->reader?->close();
        $this->ready = false;

        return true;
    }

    /**
     * Readiness flag, NOT a liveness probe.
     *
     * Returns true once db_connect() has opened the writer driver; it
     * does not round-trip to the server to verify the connection is
     * still alive. Long-running callers that need genuine liveness (e.g.
     * after a potential pg_terminate_backend or TCP RST) should issue a
     * cheap `SELECT 1` and catch DriverException instead.
     *
     * @param bool $allow_bail
     */
    public function check_connection($allow_bail = true): bool
    {
        return $this->ready;
    }

    /**
     * Engine-aware capability probe. Previously returned `false` for any
     * capability not hard-coded in the match, which meant plugins calling
     * `$wpdb->has_cap('utf8mb4')` on SQLite saw `false` even though SQLite
     * natively stores UTF-8. The lookup now consults the writer's platform
     * engine before answering, so MySQL / MariaDB report the MySQL caps
     * while SQLite / PostgreSQL report their own truthful set.
     *
     * @param string $db_cap
     * @param string $table_name
     */
    public function has_cap($db_cap, $table_name = ''): bool
    {
        $engine = $this->writer->getPlatform()->getEngine();

        // Capabilities every engine we support honours at runtime.
        $universal = ['collation', 'group_concat', 'subqueries', 'utf8', 'utf8mb4'];

        if (\in_array($db_cap, $universal, true)) {
            return true;
        }

        return match ($engine) {
            'mysql', 'mariadb' => match ($db_cap) {
                'set_charset' => true,
                'utf8mb4_520' => true,
                default => false,
            },
            'pgsql' => match ($db_cap) {
                'set_charset' => true, // pg is UTF-8 natively via client_encoding
                'utf8mb4_520' => true,
                'identifier_placeholders' => true,
                default => false,
            },
            'sqlite' => match ($db_cap) {
                // pdo_sqlite always stores UTF-8; collation maps to a
                // single BINARY/NOCASE enum but we don't expose those.
                'set_charset' => false,
                'utf8mb4_520' => true,
                default => false,
            },
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

            // APM listeners get a failure event for translation errors
            // too — the SQL never reached the driver so driverName is the
            // writer (where it would have gone) and the error text is
            // prefixed to distinguish it from driver-side failures.
            // Avoid constructing the event object (plus paramsSummary
            // allocation) when no listener is registered.
            if ($this->eventDispatcher !== null) {
                $this->eventDispatcher->dispatch(new DatabaseQueryFailedEvent(
                    sql: $sql,
                    paramsSummary: $this->paramsSummary($params),
                    errorMessage: '[Translation] ' . $e->getMessage(),
                    driverName: ($driver === $this->reader) ? 'reader' : 'writer',
                ));
            }

            // Prefix distinguishes translator failures from driver failures in last_error
            $this->last_error = '[Translation] ' . $e->getMessage();
            $this->errno = 0; // translation errors are not driver errors
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

                if ($this->eventDispatcher !== null) {
                    $this->eventDispatcher->dispatch(new DatabaseQueryFailedEvent(
                        sql: $translatedSql,
                        paramsSummary: $this->paramsSummary($params),
                        errorMessage: $e->getMessage(),
                        driverName: ($driver === $this->reader) ? 'reader' : 'writer',
                    ));
                }

                $this->last_error = $e->getMessage();
                $this->errno = $e instanceof \WPPack\Component\Database\Exception\DriverException && $e->driverErrno !== null
                    ? $e->driverErrno
                    : 0;
                $this->last_result = [];
                $this->num_rows = 0;

                return false;
            }
        }

        // Only refresh insert_id for INSERT/REPLACE queries. Other queries
        // don't produce a sequence value, and on PostgreSQL calling
        // SELECT lastval() after a non-INSERT raises an 'undefined' error
        // that silently aborts the outer transaction — the classic cause
        // of 'current transaction is aborted, commands ignored until end
        // of transaction block' following a bare BEGIN / DDL.
        if (self::isRowWriter($sql)) {
            $this->insert_id = $driver->lastInsertId();
        }
        $this->last_error = '';
        $this->errno = 0;

        $elapsed = microtime(true) - $start;
        $elapsedMs = round($elapsed * 1000, 2);
        $slowThresholdMs = $this->slowQueryThresholdMs();

        $driverName = ($driver === $this->reader) ? 'reader' : 'writer';

        if ($this->logger !== null) {
            $context = $this->buildLogContext($sql, $params, [
                'time_ms' => $elapsedMs,
                'driver' => $driverName,
            ]);

            if ($slowThresholdMs !== null && $elapsedMs >= $slowThresholdMs) {
                // Slow-query events are worth a higher log level so they
                // land in a default-production log pipeline (which usually
                // suppresses debug). Keeps the debug-stream quiet for the
                // p99 of fast queries.
                $this->logger->warning('Slow database query', $context + ['slow_threshold_ms' => $slowThresholdMs]);
            } else {
                $this->logger->debug('Query executed', $context);
            }
        }

        if ($this->eventDispatcher !== null) {
            $this->eventDispatcher->dispatch(new DatabaseQueryCompletedEvent(
                sql: $sql,
                paramsSummary: $this->paramsSummary($params),
                elapsedMs: $elapsedMs,
                rowCount: $isSelect ? \count($this->last_result) : $this->rows_affected,
                driverName: $driverName,
            ));
        }

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

            // Long-running WP-CLI workers with SAVEQUERIES enabled used to
            // grow $this->queries without bound. Trim to the env-configured
            // tail so the array stays useful for debugging without eating
            // unbounded memory.
            $max = $this->queriesMaxEntries();
            if ($max > 0 && \count($this->queries) > $max) {
                $this->queries = \array_slice($this->queries, -$max);
            }
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
     * Read the slow-query threshold (ms) from WPPACK_DB_SLOW_QUERY_MS.
     *
     * When set to a positive number, every query whose wall-clock
     * execution time meets or exceeds that threshold gets logged at
     * warning level with an extra `slow_threshold_ms` field so APM
     * pipelines can page on slow traffic. Unset / 0 / non-numeric
     * values disable the upgrade (the log stays at debug).
     */
    private function slowQueryThresholdMs(): ?float
    {
        $raw = $_SERVER['WPPACK_DB_SLOW_QUERY_MS'] ?? $_ENV['WPPACK_DB_SLOW_QUERY_MS'] ?? getenv('WPPACK_DB_SLOW_QUERY_MS');

        if (!is_numeric($raw)) {
            return null;
        }

        $threshold = (float) $raw;

        return $threshold > 0 ? $threshold : null;
    }

    /**
     * Cap on the number of SAVEQUERIES entries we retain in
     * $this->queries. Configurable via `WPPACK_DB_QUERIES_MAX`; 0 /
     * non-numeric / unset leaves the array unbounded (matching
     * standard wpdb). Default of 10_000 is large enough for typical
     * debugging needs without letting a long-running worker blow up.
     */
    private function queriesMaxEntries(): int
    {
        $raw = $_SERVER['WPPACK_DB_QUERIES_MAX'] ?? $_ENV['WPPACK_DB_QUERIES_MAX'] ?? getenv('WPPACK_DB_QUERIES_MAX');

        if (!is_numeric($raw)) {
            return 10000;
        }

        return max(0, (int) $raw);
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
     * internal WPPackWpdb frames.
     *
     * Standard wpdb::get_caller() filters out its own class, but because
     * WPPackWpdb is a subclass every frame from this class slips past
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
                $type = $frame['type'] ?? '::';
                $callers[] = "{$class}{$type}{$frame['function']}";
            } else {
                $callers[] = $frame['function'];
            }
        }

        return implode(', ', array_reverse($callers));
    }

    /**
     * Execute a COUNT(*) query to determine total rows for SQL_CALC_FOUND_ROWS.
     *
     * Strips trailing LIMIT/OFFSET and comment / semicolon noise from the
     * translated SQL before wrapping in COUNT(*). The COUNT(*) subquery
     * approach means leaving any LIMIT in place would cap the reported total
     * at the LIMIT value, so robustly normalising the tail is correctness-
     * critical. We walk the end of the string:
     *   1. rtrim whitespace + trailing semicolons
     *   2. peel off `-- ...` single-line comment and `/* ... *\/` block
     *      comment suffixes (repeat — SQL can end with multiple comments)
     *   3. drop one trailing LIMIT clause (with optional OFFSET)
     * ...then the final SQL goes into the COUNT(*) subquery.
     *
     * The regex is anchored to `$`, so LIMIT inside a derived table or CTE
     * is not matched — only the outermost clause.
     *
     * @param list<mixed> $params
     */
    private function calculateFoundRows(DriverInterface $driver, string $translatedSql, array $params): void
    {
        try {
            $countSql = $this->stripTrailingLimitClause($translatedSql);

            // Strip SQL_CALC_FOUND_ROWS — invalid inside a subquery, and the
            // enclosing COUNT(*) replaces its semantics.
            $countSql = (string) preg_replace('/\bSQL_CALC_FOUND_ROWS\b\s*/i', '', $countSql);

            // LIMIT clauses are positional and always sit at the tail of the
            // statement, so any ? placeholders that were stripped correspond
            // to the last N entries in $params. Drop them from the param
            // list so the COUNT(*) subquery binds exactly what it has.
            $placeholdersBefore = substr_count($translatedSql, '?');
            $placeholdersAfter = substr_count($countSql, '?');
            $countParams = ($placeholdersAfter < $placeholdersBefore)
                ? \array_slice($params, 0, $placeholdersAfter)
                : $params;

            $result = $driver->executeQuery(
                'SELECT COUNT(*) FROM (' . $countSql . ') AS _wppack_found',
                $countParams,
            );
            $this->lastFoundRows = (int) $result->fetchOne();
        } catch (\Throwable $e) {
            // Previous behaviour silently returned 0 here, which makes any
            // page that reads FOUND_ROWS() display 'no results found' even
            // when the main SELECT succeeded. Log the failure so operators
            // can see why pagination went sideways and reset the cached
            // count (caller reads 0 but at least there's a log trail).
            $this->logger?->error('SQL_CALC_FOUND_ROWS COUNT(*) subquery failed', [
                'sql' => $translatedSql,
                'error' => $e->getMessage(),
            ]);
            $this->lastFoundRows = 0;
        }
    }

    /**
     * Peel whitespace, semicolons, trailing comments and a final LIMIT /
     * OFFSET clause from the tail of a SELECT. Extracted from
     * calculateFoundRows for readability and for regression tests.
     */
    private function stripTrailingLimitClause(string $sql): string
    {
        $previous = '';
        $current = $sql;

        // Repeat until fixed point — a SELECT may end with whitespace,
        // then a block comment, then a line comment, then LIMIT.
        while ($current !== $previous) {
            $previous = $current;

            // Trim trailing whitespace and semicolons
            $current = rtrim($current, " \t\n\r\0\x0B;");

            // Strip trailing `-- ...` line comment (applies when no newline
            // follows, i.e. the comment is on the last line)
            $current = (string) preg_replace('/--[^\n]*$/', '', $current);

            // Strip trailing `/* ... */` block comment
            $current = (string) preg_replace('/\/\*.*?\*\/\s*$/s', '', $current);
        }

        // Now drop the outermost trailing LIMIT [OFFSET] clause, tolerating
        // `LIMIT n`, `LIMIT n,m`, and `LIMIT n OFFSET m` forms.
        return (string) preg_replace(
            '/\bLIMIT\s+[^()]+?\s*$/i',
            '',
            $current,
        );
    }

    private function isSelectQuery(string $sql): bool
    {
        $trimmed = ltrim($sql);

        return (bool) preg_match('/^(SELECT|SHOW|DESCRIBE|EXPLAIN|PRAGMA)\b/i', $trimmed);
    }

    /**
     * Detect queries that produce a new auto-increment / sequence value,
     * i.e. the only ones for which lastInsertId() is meaningful. Gating
     * the post-query lastInsertId() call on this check avoids PostgreSQL's
     * SELECT lastval() raising 'undefined' — inside a transaction that
     * error silently aborts everything the caller ran afterwards.
     */
    private static function isRowWriter(string $sql): bool
    {
        $trimmed = ltrim($sql);

        return (bool) preg_match('/^(INSERT|REPLACE)\b/i', $trimmed);
    }

    private function selectDriver(string $sql): DriverInterface
    {
        if ($this->reader === null) {
            return $this->writer;
        }

        // Read-your-own-writes: once any write / transaction command has
        // reached the writer in this request, every subsequent query —
        // including reads — must stay on the writer until the request ends.
        // Otherwise a SELECT right after an INSERT could hit a replica that
        // has not yet replayed the commit, returning stale data.
        if ($this->stickyWriter) {
            return $this->writer;
        }

        $trimmed = ltrim($sql);

        // Transaction-control statements always belong on the writer and
        // pin subsequent traffic to the writer too.
        if (preg_match('/^(BEGIN|START\s+TRANSACTION|SAVEPOINT)\b/i', $trimmed)) {
            $this->stickyWriter = true;

            return $this->writer;
        }

        // Keep this list in sync with isSelectQuery(); PRAGMA is a read-like
        // SQLite statement whose result shape matches SELECT, so route it to
        // the reader when available.
        if (preg_match('/^(SELECT|SHOW|DESCRIBE|EXPLAIN|PRAGMA)\b/i', $trimmed)) {
            return $this->reader;
        }

        // Anything else (INSERT/UPDATE/DELETE/REPLACE/DDL/SET/COMMIT/ROLLBACK)
        // is a writer-bound query. Pin subsequent SELECTs to the writer too.
        $this->stickyWriter = true;

        return $this->writer;
    }

    /**
     * Reset the reader/writer stickiness. Intended for long-running processes
     * (WP-CLI workers, queue consumers) that want to release the writer after
     * finishing a unit of work, typically just after a commit or explicit
     * checkpoint. Production web requests should leave this alone — they want
     * the sticky state to persist until the request ends.
     */
    public function resetReaderStickiness(): void
    {
        $this->stickyWriter = false;
    }

    /**
     * Track BEGIN / COMMIT / ROLLBACK / SAVEPOINT nesting so we can warn
     * about classic MySQL footguns — notably that a nested BEGIN silently
     * commits the outer transaction. Depth is a diagnostic only; sticky
     * writer affinity stays on for the rest of the request regardless.
     */
    private function updateTransactionDepth(string $sql): void
    {
        $trimmed = ltrim($sql);

        if (preg_match('/^(BEGIN|START\s+TRANSACTION|SAVEPOINT)\b/i', $trimmed)) {
            if ($this->transactionDepth > 0) {
                // On MySQL, issuing BEGIN inside an active transaction
                // silently commits the previous one. On PostgreSQL it
                // raises an error. SAVEPOINT is the intended primitive
                // for nesting; warn so operators can spot the bug.
                $this->logger?->warning('Nested transaction BEGIN detected — MySQL will auto-commit the outer transaction', [
                    'sql' => $trimmed,
                    'previous_depth' => $this->transactionDepth,
                ]);
            }
            $this->transactionDepth++;

            return;
        }

        // `ROLLBACK TO SAVEPOINT sp1` rewinds to the savepoint but the outer
        // transaction is still alive — it must NOT decrement depth. Only a
        // bare COMMIT / ROLLBACK / RELEASE SAVEPOINT ends a nesting level.
        // Match 'ROLLBACK' only when the next token is not 'TO'.
        if (preg_match('/^(COMMIT|RELEASE\s+SAVEPOINT)\b/i', $trimmed)
            || preg_match('/^ROLLBACK\b(?!\s+TO\b)/i', $trimmed)) {
            $this->transactionDepth = max(0, $this->transactionDepth - 1);
        }
    }

    /**
     * Current transaction nesting depth as tracked by updateTransactionDepth().
     * Exposed for tests and for callers that want to know whether we are
     * inside a transaction (e.g. to decide retry policy).
     */
    public function getTransactionDepth(): int
    {
        return $this->transactionDepth;
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
     * Run wpdb's field validation (format / charset / length) when the
     * underlying connection is mysqli — the exact pre-flight checks
     * wpdb::_insert_replace_helper() would normally apply — and flatten the
     * resulting tagged structure back to [column => value] for our own
     * executeInsertReplace() / update() paths.
     *
     * Returning null mirrors the standard wpdb behavior (validation rejected,
     * the caller must abort so wp_insert_post() can report a WP_Error) —
     * process_fields() itself returns false, which we normalise to null for
     * a modern ?array contract on this private helper.
     *
     * For non-mysqli drivers (SQLite, PostgreSQL, Aurora Data API) the
     * DESCRIBE / SHOW FULL COLUMNS queries that feed process_fields are not
     * available, so we skip the validation and trust the driver to surface
     * an error at execute time.
     *
     * @param array<string, mixed>      $data
     * @param array<string>|string|null $format
     *
     * @return array<string, mixed>|null
     */
    private function validateAndFlatten(string $table, array $data, array|string|null $format): ?array
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
            return null;
        }

        $flat = [];

        foreach ($processed as $column => $info) {
            $flat[$column] = \is_array($info) && \array_key_exists('value', $info) ? $info['value'] : $info;
        }

        return $flat;
    }

    /**
     * Build and execute an INSERT / REPLACE statement via the writer Driver.
     *
     * Field-level validation (format, charset, length) is handled upstream in
     * validateAndFlatten(), so this helper only needs the flattened
     * [column => value] map — no $format here.
     *
     * @param array<string, mixed> $data
     */
    private function executeInsertReplace(string $table, array $data, string $verb): int|false
    {
        if ($data === []) {
            return false;
        }

        $platform = $this->writer->getPlatform();
        $quotedTable = $platform->quoteIdentifier($table);

        $columns = [];
        $placeholders = [];

        foreach (array_keys($data) as $column) {
            $columns[] = $platform->quoteIdentifier($column);
            $placeholders[] = '?';
        }

        $sql = \sprintf(
            '%s INTO %s (%s) VALUES (%s)',
            $verb,
            $quotedTable,
            implode(', ', $columns),
            implode(', ', $placeholders),
        );

        $result = $this->executeWithDriver($sql, array_values($data));

        return $result === false ? false : (int) $result;
    }
}
