<?php

declare(strict_types=1);

namespace WpPack\Component\Database;

use WpPack\Component\Database\Exception\QueryException;

final class DatabaseManager
{
    public readonly string $posts;
    public readonly string $postmeta;
    public readonly string $comments;
    public readonly string $commentmeta;
    public readonly string $options;
    public readonly string $users;
    public readonly string $usermeta;
    public readonly string $terms;
    public readonly string $termmeta;
    public readonly string $termTaxonomy;
    public readonly string $termRelationships;

    private \wpdb $wpdb;

    public function __construct()
    {
        global $wpdb;

        $this->wpdb = $wpdb;
        $this->posts = $wpdb->posts;
        $this->postmeta = $wpdb->postmeta;
        $this->comments = $wpdb->comments;
        $this->commentmeta = $wpdb->commentmeta;
        $this->options = $wpdb->options;
        $this->users = $wpdb->users;
        $this->usermeta = $wpdb->usermeta;
        $this->terms = $wpdb->terms;
        $this->termmeta = $wpdb->termmeta;
        $this->termTaxonomy = $wpdb->term_taxonomy;
        $this->termRelationships = $wpdb->term_relationships;
    }

    /**
     * Execute a SELECT query and return the result of $wpdb->query().
     *
     * Use fetch methods to retrieve results after calling this method.
     *
     * @throws QueryException
     */
    public function executeQuery(string $query, mixed ...$args): int|bool
    {
        $sql = $this->prepareIfNeeded($query, $args);
        $result = $this->wpdb->query($sql);

        if ($result === false) {
            throw new QueryException($sql, $this->wpdb->last_error);
        }

        return $result;
    }

    /**
     * Execute an INSERT, UPDATE, or DELETE statement and return the number of affected rows.
     *
     * @throws QueryException
     */
    public function executeStatement(string $query, mixed ...$args): int
    {
        $sql = $this->prepareIfNeeded($query, $args);
        $result = $this->wpdb->query($sql);

        if ($result === false) {
            throw new QueryException($sql, $this->wpdb->last_error);
        }

        return (int) $result;
    }

    /**
     * Fetch all rows as an array of associative arrays.
     *
     * @return list<array<string, mixed>>
     *
     * @throws QueryException
     */
    public function fetchAllAssociative(string $query, mixed ...$args): array
    {
        $sql = $this->prepareIfNeeded($query, $args);
        $this->wpdb->suppress_errors(true);
        $results = $this->wpdb->get_results($sql, ARRAY_A);
        $this->wpdb->suppress_errors(false);

        if ($results === null) {
            throw new QueryException($sql, $this->wpdb->last_error);
        }

        /** @var list<array<string, mixed>> */
        return $results;
    }

    /**
     * Fetch a single row as an associative array.
     *
     * @return array<string, mixed>|false
     *
     * @throws QueryException
     */
    public function fetchAssociative(string $query, mixed ...$args): array|false
    {
        $sql = $this->prepareIfNeeded($query, $args);
        $this->wpdb->suppress_errors(true);
        $row = $this->wpdb->get_row($sql, ARRAY_A);
        $this->wpdb->suppress_errors(false);

        if ($row === null && $this->wpdb->last_error !== '') {
            throw new QueryException($sql, $this->wpdb->last_error);
        }

        return $row ?? false;
    }

    /**
     * Fetch a single scalar value.
     *
     * @throws QueryException
     */
    public function fetchOne(string $query, mixed ...$args): mixed
    {
        $sql = $this->prepareIfNeeded($query, $args);
        $this->wpdb->suppress_errors(true);
        $value = $this->wpdb->get_var($sql);
        $this->wpdb->suppress_errors(false);

        if ($value === null && $this->wpdb->last_error !== '') {
            throw new QueryException($sql, $this->wpdb->last_error);
        }

        return $value;
    }

    /**
     * Fetch the first column of all rows.
     *
     * @return list<mixed>
     *
     * @throws QueryException
     */
    public function fetchFirstColumn(string $query, mixed ...$args): array
    {
        $sql = $this->prepareIfNeeded($query, $args);
        $this->wpdb->suppress_errors(true);
        $results = $this->wpdb->get_col($sql);
        $this->wpdb->suppress_errors(false);

        if ($results === [] && $this->wpdb->last_error !== '') {
            throw new QueryException($sql, $this->wpdb->last_error);
        }

        return $results;
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

    /**
     * @param list<mixed> $args
     */
    private function prepareIfNeeded(string $query, array $args): string
    {
        if ($args === []) {
            return $query;
        }

        return $this->wpdb->prepare($query, ...$args);
    }
}
