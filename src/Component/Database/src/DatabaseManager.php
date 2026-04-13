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

use WpPack\Component\Database\Exception\DriverException;
use WpPack\Component\Database\Exception\QueryException;

/**
 * @property-read string $posts
 * @property-read string $postmeta
 * @property-read string $comments
 * @property-read string $commentmeta
 * @property-read string $options
 * @property-read string $terms
 * @property-read string $termmeta
 * @property-read string $termTaxonomy
 * @property-read string $termRelationships
 */
final class DatabaseManager
{
    public readonly DatabaseEngine $engine;
    public readonly string $users;
    public readonly string $usermeta;

    private \wpdb $wpdb;
    private Connection $connection;

    /**
     * Map of DatabaseManager property names (camelCase) to wpdb property names (snake_case).
     *
     * These are blog-specific tables whose names change on switch_to_blog().
     *
     * @var array<string, string>
     */
    private const TABLE_MAP = [
        'posts' => 'posts',
        'postmeta' => 'postmeta',
        'comments' => 'comments',
        'commentmeta' => 'commentmeta',
        'options' => 'options',
        'terms' => 'terms',
        'termmeta' => 'termmeta',
        'termTaxonomy' => 'term_taxonomy',
        'termRelationships' => 'term_relationships',
    ];

    public function __construct()
    {
        global $wpdb;

        $this->wpdb = $wpdb;
        $this->engine = match (true) {
            $wpdb->dbh instanceof \mysqli => DatabaseEngine::MySQL,
            $wpdb->dbh instanceof \PgSql\Connection => DatabaseEngine::PostgreSQL,
            default => DatabaseEngine::SQLite,
        };
        $this->users = $wpdb->users;
        $this->usermeta = $wpdb->usermeta;
        $this->connection = $this->createConnection();
    }

    /**
     * Proxy blog-specific table names to $wpdb for multisite switch_to_blog() support.
     */
    public function __get(string $name): string
    {
        if (!isset(self::TABLE_MAP[$name])) {
            throw new \InvalidArgumentException(
                sprintf('Undefined property: %s::$%s', self::class, $name),
            );
        }

        return $this->wpdb->{self::TABLE_MAP[$name]};
    }

    public function __isset(string $name): bool
    {
        return isset(self::TABLE_MAP[$name]);
    }

    /**
     * Execute a SELECT query and return the result of $wpdb->query().
     *
     * Use fetch methods to retrieve results after calling this method.
     *
     * @param list<mixed> $params
     *
     * @throws QueryException
     */
    public function executeQuery(string $query, array $params = []): true
    {
        [$sql, $nativeParams] = $this->toNativePlaceholders($query, $params);

        try {
            $this->connection->executeQuery($sql, $nativeParams);
        } catch (DriverException $e) {
            throw new QueryException($sql, $e->getMessage(), $e);
        }

        return true;
    }

    /**
     * Execute an INSERT, UPDATE, or DELETE statement and return the number of affected rows.
     *
     * @param list<mixed> $params
     *
     * @throws QueryException
     */
    public function executeStatement(string $query, array $params = []): int
    {
        [$sql, $nativeParams] = $this->toNativePlaceholders($query, $params);

        try {
            return $this->connection->executeStatement($sql, $nativeParams);
        } catch (DriverException $e) {
            throw new QueryException($sql, $e->getMessage(), $e);
        }
    }

    /**
     * Fetch all rows as an array of associative arrays.
     *
     * @param list<mixed> $params
     *
     * @return list<array<string, mixed>>
     *
     * @throws QueryException
     */
    public function fetchAllAssociative(string $query, array $params = []): array
    {
        [$sql, $nativeParams] = $this->toNativePlaceholders($query, $params);

        try {
            return $this->connection->fetchAllAssociative($sql, $nativeParams);
        } catch (DriverException $e) {
            throw new QueryException($sql, $e->getMessage(), $e);
        }
    }

    /**
     * Fetch a single row as an associative array.
     *
     * @param list<mixed> $params
     *
     * @return array<string, mixed>|null
     *
     * @throws QueryException
     */
    public function fetchAssociative(string $query, array $params = []): ?array
    {
        [$sql, $nativeParams] = $this->toNativePlaceholders($query, $params);

        try {
            return $this->connection->fetchAssociative($sql, $nativeParams);
        } catch (DriverException $e) {
            throw new QueryException($sql, $e->getMessage(), $e);
        }
    }

    /**
     * Fetch a single scalar value.
     *
     * @param list<mixed> $params
     *
     * @throws QueryException
     */
    public function fetchOne(string $query, array $params = []): mixed
    {
        [$sql, $nativeParams] = $this->toNativePlaceholders($query, $params);

        try {
            return $this->connection->fetchOne($sql, $nativeParams);
        } catch (DriverException $e) {
            throw new QueryException($sql, $e->getMessage(), $e);
        }
    }

    /**
     * Fetch the first column of all rows.
     *
     * @param list<mixed> $params
     *
     * @return list<mixed>
     *
     * @throws QueryException
     */
    public function fetchFirstColumn(string $query, array $params = []): array
    {
        [$sql, $nativeParams] = $this->toNativePlaceholders($query, $params);

        try {
            return $this->connection->fetchFirstColumn($sql, $nativeParams);
        } catch (DriverException $e) {
            throw new QueryException($sql, $e->getMessage(), $e);
        }
    }

    /**
     * Insert a row into a table. The table name is automatically prefixed.
     *
     * @param array<string, mixed>        $data
     * @param array<string>|string|null $format
     *
     * @return int The number of rows inserted (1 on success).
     *
     * @throws QueryException
     */
    public function insert(string $table, array $data, array|string|null $format = null): int
    {
        $prefixedTable = $this->wpdb->prefix . $table;
        $result = $this->wpdb->insert($prefixedTable, $data, $format);

        if ($result === false) {
            throw new QueryException(
                "INSERT INTO {$prefixedTable}",
                $this->wpdb->last_error,
            );
        }

        return $result;
    }

    /**
     * Update rows in a table. The table name is automatically prefixed.
     *
     * @param array<string, mixed>        $data
     * @param array<string, mixed>        $where
     * @param array<string>|string|null $format
     * @param array<string>|string|null $whereFormat
     *
     * @return int The number of rows updated.
     *
     * @throws QueryException
     */
    public function update(
        string $table,
        array $data,
        array $where,
        array|string|null $format = null,
        array|string|null $whereFormat = null,
    ): int {
        $prefixedTable = $this->wpdb->prefix . $table;
        $result = $this->wpdb->update($prefixedTable, $data, $where, $format, $whereFormat);

        if ($result === false) {
            throw new QueryException(
                "UPDATE {$prefixedTable}",
                $this->wpdb->last_error,
            );
        }

        return $result;
    }

    /**
     * Delete rows from a table. The table name is automatically prefixed.
     *
     * @param array<string, mixed>        $where
     * @param array<string>|string|null $whereFormat
     *
     * @return int The number of rows deleted.
     *
     * @throws QueryException
     */
    public function delete(string $table, array $where, array|string|null $whereFormat = null): int
    {
        $prefixedTable = $this->wpdb->prefix . $table;
        $result = $this->wpdb->delete($prefixedTable, $where, $whereFormat);

        if ($result === false) {
            throw new QueryException(
                "DELETE FROM {$prefixedTable}",
                $this->wpdb->last_error,
            );
        }

        return $result;
    }

    /**
     * @throws QueryException
     */
    public function beginTransaction(): void
    {
        $result = $this->wpdb->query('START TRANSACTION');

        if ($result === false) {
            throw new QueryException('START TRANSACTION', $this->wpdb->last_error);
        }
    }

    /**
     * @throws QueryException
     */
    public function commit(): void
    {
        $result = $this->wpdb->query('COMMIT');

        if ($result === false) {
            throw new QueryException('COMMIT', $this->wpdb->last_error);
        }
    }

    /**
     * @throws QueryException
     */
    public function rollBack(): void
    {
        $result = $this->wpdb->query('ROLLBACK');

        if ($result === false) {
            throw new QueryException('ROLLBACK', $this->wpdb->last_error);
        }
    }

    public function prepare(string $query, mixed ...$args): string
    {
        return $this->wpdb->prepare($query, ...$args);
    }

    /**
     * Quote an identifier (table name, column name) with backticks.
     */
    public function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    public function prefix(): string
    {
        return $this->wpdb->prefix;
    }

    /**
     * Return the base table prefix (network-wide, not blog-specific).
     *
     * On single-site this is the same as prefix(). On multisite, prefix()
     * returns a blog-specific prefix (e.g. "wp_2_") while basePrefix()
     * always returns the root prefix (e.g. "wp_").
     */
    public function basePrefix(): string
    {
        return $this->wpdb->base_prefix;
    }

    public function charsetCollate(): string
    {
        return $this->wpdb->get_charset_collate();
    }

    public function lastInsertId(): int
    {
        return (int) $this->wpdb->insert_id;
    }

    public function lastError(): string
    {
        return $this->wpdb->last_error;
    }

    public function wpdb(): \wpdb
    {
        return $this->wpdb;
    }

    public function getConnection(): Connection
    {
        return $this->connection;
    }

    /**
     * Create a Connection from the current wpdb handle.
     *
     * Wraps the existing $wpdb->dbh (mysqli, PDO, PgSql) in a Driver,
     * enabling native prepared statements for all engines.
     */
    private function createConnection(): Connection
    {
        /** @phpstan-ignore property.protected */
        $dbh = $this->wpdb->dbh;

        if ($dbh instanceof \mysqli) {
            return new Connection(Driver\MysqlDriver::fromMysqli($dbh));
        }

        // Fallback: create new connection from WordPress constants
        return new Connection(new Driver\MysqlDriver(
            host: \defined('DB_HOST') ? DB_HOST : '127.0.0.1',
            username: \defined('DB_USER') ? DB_USER : 'root',
            password: \defined('DB_PASSWORD') ? DB_PASSWORD : '',
            database: \defined('DB_NAME') ? DB_NAME : '',
        ));
    }

    /**
     * Convert WordPress-style placeholders (%s, %d, %f) to native ? placeholders.
     *
     * If the query already uses ? placeholders, it passes through unchanged.
     * If the query uses %s/%d/%f, they are converted to ? for Connection-based execution.
     * Literal %% is preserved.
     *
     * @param list<mixed> $params
     *
     * @return array{string, list<mixed>}
     */
    private function toNativePlaceholders(string $query, array $params): array
    {
        // Already uses ? placeholders — pass through
        if (str_contains($query, '?') || !preg_match('/%[sdf]/', $query)) {
            return [$query, $params];
        }

        // Convert %s/%d/%f to ? (same logic as convertPlaceholders but without type string)
        $sql = (string) preg_replace('/%%/', "\x00LITERAL_PERCENT\x00", $query);
        $sql = (string) preg_replace('/%[sdf]/', '?', $sql);
        $sql = str_replace("\x00LITERAL_PERCENT\x00", '%%', $sql);

        return [$sql, $params];
    }
}
