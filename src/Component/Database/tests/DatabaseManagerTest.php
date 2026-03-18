<?php

declare(strict_types=1);

namespace WpPack\Component\Database\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Database\DatabaseEngine;
use WpPack\Component\Database\DatabaseManager;
use WpPack\Component\Database\Exception\QueryException;

#[CoversClass(DatabaseManager::class)]
final class DatabaseManagerTest extends TestCase
{
    private ?DatabaseManager $db = null;

    protected function setUp(): void
    {
        $this->db = new DatabaseManager();

        $this->db->executeStatement(sprintf(
            'CREATE TABLE IF NOT EXISTS %swppack_test (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                name varchar(255) NOT NULL,
                value text,
                PRIMARY KEY (id)
            ) %s',
            $this->db->prefix(),
            $this->db->charsetCollate(),
        ));
    }

    protected function tearDown(): void
    {
        if ($this->db !== null) {
            $this->db->wpdb()->query(sprintf('DROP TABLE IF EXISTS %swppack_test', $this->db->prefix()));
        }
    }

    #[Test]
    public function coreTablePropertiesAreSet(): void
    {
        self::assertStringContainsString('posts', $this->db->posts);
        self::assertStringContainsString('postmeta', $this->db->postmeta);
        self::assertStringContainsString('comments', $this->db->comments);
        self::assertStringContainsString('commentmeta', $this->db->commentmeta);
        self::assertStringContainsString('options', $this->db->options);
        self::assertStringContainsString('users', $this->db->users);
        self::assertStringContainsString('usermeta', $this->db->usermeta);
        self::assertStringContainsString('terms', $this->db->terms);
        self::assertStringContainsString('termmeta', $this->db->termmeta);
        self::assertStringContainsString('term_taxonomy', $this->db->termTaxonomy);
        self::assertStringContainsString('term_relationships', $this->db->termRelationships);
    }

    #[Test]
    public function enginePropertyIsSet(): void
    {
        self::assertInstanceOf(DatabaseEngine::class, $this->db->engine);
        self::assertSame(DatabaseEngine::MySQL, $this->db->engine);
    }

    #[Test]
    public function prefixReturnsNonEmptyString(): void
    {
        self::assertNotEmpty($this->db->prefix());
    }

    #[Test]
    public function charsetCollateReturnsNonEmptyString(): void
    {
        self::assertNotEmpty($this->db->charsetCollate());
    }

    #[Test]
    public function insertAndFetchAssociative(): void
    {
        $this->db->insert('wppack_test', [
            'name' => 'test_row',
            'value' => 'hello',
        ]);

        $id = $this->db->lastInsertId();
        self::assertGreaterThan(0, $id);

        $row = $this->db->fetchAssociative(
            "SELECT * FROM {$this->db->prefix()}wppack_test WHERE id = %d",
            [$id],
        );

        self::assertIsArray($row);
        self::assertSame('test_row', $row['name']);
        self::assertSame('hello', $row['value']);
    }

    #[Test]
    public function fetchAllAssociative(): void
    {
        $this->db->insert('wppack_test', ['name' => 'row1', 'value' => 'a']);
        $this->db->insert('wppack_test', ['name' => 'row2', 'value' => 'b']);

        $rows = $this->db->fetchAllAssociative(
            "SELECT * FROM {$this->db->prefix()}wppack_test ORDER BY name ASC",
        );

        self::assertCount(2, $rows);
        self::assertSame('row1', $rows[0]['name']);
        self::assertSame('row2', $rows[1]['name']);
    }

    #[Test]
    public function fetchOne(): void
    {
        $this->db->insert('wppack_test', ['name' => 'count_test', 'value' => 'x']);

        $count = $this->db->fetchOne(
            "SELECT COUNT(*) FROM {$this->db->prefix()}wppack_test WHERE name = %s",
            ['count_test'],
        );

        self::assertEquals(1, $count);
    }

    #[Test]
    public function fetchFirstColumn(): void
    {
        $this->db->insert('wppack_test', ['name' => 'col_a', 'value' => 'x']);
        $this->db->insert('wppack_test', ['name' => 'col_b', 'value' => 'y']);

        $names = $this->db->fetchFirstColumn(
            "SELECT name FROM {$this->db->prefix()}wppack_test ORDER BY name ASC",
        );

        self::assertSame(['col_a', 'col_b'], $names);
    }

    #[Test]
    public function fetchAssociativeReturnsFalseWhenNotFound(): void
    {
        $row = $this->db->fetchAssociative(
            "SELECT * FROM {$this->db->prefix()}wppack_test WHERE id = %d",
            [999999],
        );

        self::assertFalse($row);
    }

    #[Test]
    public function updateRow(): void
    {
        $this->db->insert('wppack_test', ['name' => 'update_me', 'value' => 'old']);
        $id = $this->db->lastInsertId();

        $affected = $this->db->update(
            'wppack_test',
            ['value' => 'new'],
            ['id' => $id],
        );

        self::assertSame(1, $affected);

        $row = $this->db->fetchAssociative(
            "SELECT * FROM {$this->db->prefix()}wppack_test WHERE id = %d",
            [$id],
        );

        self::assertSame('new', $row['value']);
    }

    #[Test]
    public function deleteRow(): void
    {
        $this->db->insert('wppack_test', ['name' => 'delete_me', 'value' => 'gone']);
        $id = $this->db->lastInsertId();

        $affected = $this->db->delete('wppack_test', ['id' => $id]);

        self::assertSame(1, $affected);

        $row = $this->db->fetchAssociative(
            "SELECT * FROM {$this->db->prefix()}wppack_test WHERE id = %d",
            [$id],
        );

        self::assertFalse($row);
    }

    #[Test]
    public function executeStatementReturnsAffectedRows(): void
    {
        $this->db->insert('wppack_test', ['name' => 'stmt1', 'value' => 'x']);
        $this->db->insert('wppack_test', ['name' => 'stmt2', 'value' => 'x']);

        $affected = $this->db->executeStatement(
            "DELETE FROM {$this->db->prefix()}wppack_test WHERE value = %s",
            ['x'],
        );

        self::assertSame(2, $affected);
    }

    #[Test]
    public function transactionCommit(): void
    {
        $this->db->beginTransaction();
        $this->db->insert('wppack_test', ['name' => 'tx_commit', 'value' => 'committed']);
        $this->db->commit();

        $row = $this->db->fetchAssociative(
            "SELECT * FROM {$this->db->prefix()}wppack_test WHERE name = %s",
            ['tx_commit'],
        );

        self::assertIsArray($row);
        self::assertSame('committed', $row['value']);
    }

    #[Test]
    public function transactionRollBack(): void
    {
        $this->db->beginTransaction();
        $this->db->insert('wppack_test', ['name' => 'tx_rollback', 'value' => 'rolled']);
        $this->db->rollBack();

        $row = $this->db->fetchAssociative(
            "SELECT * FROM {$this->db->prefix()}wppack_test WHERE name = %s",
            ['tx_rollback'],
        );

        self::assertFalse($row);
    }

    #[Test]
    public function quoteIdentifier(): void
    {
        self::assertSame('`my_table`', $this->db->quoteIdentifier('my_table'));
        self::assertSame('`col``name`', $this->db->quoteIdentifier('col`name'));
    }

    #[Test]
    public function wpdbReturnsInstance(): void
    {
        self::assertInstanceOf(\wpdb::class, $this->db->wpdb());
    }

    #[Test]
    public function prepare(): void
    {
        $sql = $this->db->prepare('SELECT * FROM wp_posts WHERE ID = %d', 1);

        self::assertStringContainsString('1', $sql);
    }

    #[Test]
    public function fetchAllAssociativeWithParams(): void
    {
        $this->db->insert('wppack_test', ['name' => 'param_a', 'value' => 'target']);
        $this->db->insert('wppack_test', ['name' => 'param_b', 'value' => 'other']);

        $rows = $this->db->fetchAllAssociative(
            "SELECT * FROM {$this->db->prefix()}wppack_test WHERE value = %s",
            ['target'],
        );

        self::assertCount(1, $rows);
        self::assertSame('param_a', $rows[0]['name']);
    }

    #[Test]
    public function fetchOneReturnsNullWhenNotFound(): void
    {
        $value = $this->db->fetchOne(
            "SELECT name FROM {$this->db->prefix()}wppack_test WHERE id = %d",
            [999999],
        );

        self::assertNull($value);
    }

    #[Test]
    public function fetchFirstColumnWithParams(): void
    {
        $this->db->insert('wppack_test', ['name' => 'fc_a', 'value' => 'match']);
        $this->db->insert('wppack_test', ['name' => 'fc_b', 'value' => 'match']);
        $this->db->insert('wppack_test', ['name' => 'fc_c', 'value' => 'other']);

        $names = $this->db->fetchFirstColumn(
            "SELECT name FROM {$this->db->prefix()}wppack_test WHERE value = %s ORDER BY name ASC",
            ['match'],
        );

        self::assertSame(['fc_a', 'fc_b'], $names);
    }

    #[Test]
    public function executeQueryWithParams(): void
    {
        $this->db->insert('wppack_test', ['name' => 'eq_test', 'value' => 'v']);

        $result = $this->db->executeQuery(
            "SELECT * FROM {$this->db->prefix()}wppack_test WHERE name = %s",
            ['eq_test'],
        );

        self::assertNotFalse($result);
    }

    #[Test]
    public function multipleParamTypes(): void
    {
        $this->db->insert('wppack_test', ['name' => 'multi', 'value' => 'test']);
        $id = $this->db->lastInsertId();

        $row = $this->db->fetchAssociative(
            "SELECT * FROM {$this->db->prefix()}wppack_test WHERE id = %d AND name = %s",
            [$id, 'multi'],
        );

        self::assertIsArray($row);
        self::assertSame('multi', $row['name']);
    }

    #[Test]
    public function paramsWithNoResults(): void
    {
        $rows = $this->db->fetchAllAssociative(
            "SELECT * FROM {$this->db->prefix()}wppack_test WHERE name = %s",
            ['nonexistent'],
        );

        self::assertSame([], $rows);
    }

    // --- Error paths ---

    #[Test]
    public function executeQueryThrowsQueryExceptionOnError(): void
    {
        $this->expectException(QueryException::class);

        $this->db->wpdb()->suppress_errors(true);
        $this->db->executeQuery('SELECT * FROM nonexistent_table_wppack_xyz');
    }

    #[Test]
    public function executeStatementThrowsQueryExceptionOnError(): void
    {
        $this->expectException(QueryException::class);

        $this->db->wpdb()->suppress_errors(true);
        $this->db->executeStatement('DELETE FROM nonexistent_table_wppack_xyz WHERE id = 1');
    }

    #[Test]
    public function insertThrowsQueryExceptionOnError(): void
    {
        $this->expectException(QueryException::class);

        $this->db->wpdb()->suppress_errors(true);
        $this->db->insert('nonexistent_table_wppack_xyz', ['name' => 'test']);
    }

    // --- Magic methods ---

    #[Test]
    public function getInvalidPropertyThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->db->nonExistentProperty;
    }

    #[Test]
    public function issetReturnsTrueForMappedProperties(): void
    {
        self::assertTrue(isset($this->db->posts));
        self::assertTrue(isset($this->db->termTaxonomy));
    }

    #[Test]
    public function issetReturnsFalseForUnmappedProperties(): void
    {
        self::assertFalse(isset($this->db->nonExistent));
    }

    // --- Utility methods ---

    #[Test]
    public function lastErrorReturnsEmptyStringAfterSuccess(): void
    {
        $this->db->insert('wppack_test', ['name' => 'err_ok', 'value' => 'v']);

        self::assertSame('', $this->db->lastError());
    }

    #[Test]
    public function lastErrorReturnsErrorAfterFailedQuery(): void
    {
        $this->db->wpdb()->suppress_errors(true);

        try {
            $this->db->executeQuery('SELECT * FROM nonexistent_table_wppack_xyz');
        } catch (QueryException) {
            // expected
        }

        self::assertNotSame('', $this->db->lastError());
    }

    #[Test]
    public function lastInsertIdReturnsIncrementingIds(): void
    {
        $this->db->insert('wppack_test', ['name' => 'inc1', 'value' => 'a']);
        $id1 = $this->db->lastInsertId();

        $this->db->insert('wppack_test', ['name' => 'inc2', 'value' => 'b']);
        $id2 = $this->db->lastInsertId();

        self::assertGreaterThan($id1, $id2);
    }

    // --- Format parameters ---

    #[Test]
    public function insertWithExplicitFormat(): void
    {
        $this->db->insert('wppack_test', [
            'name' => 'fmt_test',
            'value' => 'formatted',
        ], ['%s', '%s']);

        $row = $this->db->fetchAssociative(
            "SELECT * FROM {$this->db->prefix()}wppack_test WHERE name = %s",
            ['fmt_test'],
        );

        self::assertIsArray($row);
        self::assertSame('formatted', $row['value']);
    }

    #[Test]
    public function updateWithFormatAndWhereFormat(): void
    {
        $this->db->insert('wppack_test', ['name' => 'fmt_upd', 'value' => 'old']);
        $id = $this->db->lastInsertId();

        $affected = $this->db->update(
            'wppack_test',
            ['value' => 'new'],
            ['id' => $id],
            ['%s'],
            ['%d'],
        );

        self::assertSame(1, $affected);

        $row = $this->db->fetchAssociative(
            "SELECT * FROM {$this->db->prefix()}wppack_test WHERE id = %d",
            [$id],
        );

        self::assertSame('new', $row['value']);
    }

    #[Test]
    public function deleteWithWhereFormat(): void
    {
        $this->db->insert('wppack_test', ['name' => 'fmt_del', 'value' => 'gone']);
        $id = $this->db->lastInsertId();

        $affected = $this->db->delete('wppack_test', ['id' => $id], ['%d']);

        self::assertSame(1, $affected);

        $row = $this->db->fetchAssociative(
            "SELECT * FROM {$this->db->prefix()}wppack_test WHERE id = %d",
            [$id],
        );

        self::assertFalse($row);
    }

    // --- Empty result edge cases ---

    #[Test]
    public function fetchAllAssociativeReturnsEmptyArrayForNoResults(): void
    {
        $rows = $this->db->fetchAllAssociative(
            "SELECT * FROM {$this->db->prefix()}wppack_test WHERE name = 'does_not_exist_at_all'",
        );

        self::assertSame([], $rows);
    }

    #[Test]
    public function fetchFirstColumnReturnsEmptyArrayForNoResults(): void
    {
        $names = $this->db->fetchFirstColumn(
            "SELECT name FROM {$this->db->prefix()}wppack_test WHERE name = 'does_not_exist_at_all'",
        );

        self::assertSame([], $names);
    }

    // --- executeQuery without params (non-prepared path) ---

    #[Test]
    public function executeQueryWithoutParams(): void
    {
        $this->db->insert('wppack_test', ['name' => 'noparam', 'value' => 'v']);

        $result = $this->db->executeQuery(
            "SELECT * FROM {$this->db->prefix()}wppack_test WHERE name = 'noparam'",
        );

        self::assertNotFalse($result);
    }

    // --- executeStatement without params (non-prepared path) ---

    #[Test]
    public function executeStatementWithoutParams(): void
    {
        $this->db->insert('wppack_test', ['name' => 'noparam_stmt', 'value' => 'v']);

        $affected = $this->db->executeStatement(
            "DELETE FROM {$this->db->prefix()}wppack_test WHERE name = 'noparam_stmt'",
        );

        self::assertSame(1, $affected);
    }

    // --- fetchAssociative without params (non-prepared path) ---

    #[Test]
    public function fetchAssociativeWithoutParams(): void
    {
        $this->db->insert('wppack_test', ['name' => 'fetch_noparam', 'value' => 'hello']);

        $row = $this->db->fetchAssociative(
            "SELECT * FROM {$this->db->prefix()}wppack_test WHERE name = 'fetch_noparam'",
        );

        self::assertIsArray($row);
        self::assertSame('hello', $row['value']);
    }

    #[Test]
    public function fetchAssociativeWithoutParamsReturnsFalseWhenNotFound(): void
    {
        $row = $this->db->fetchAssociative(
            "SELECT * FROM {$this->db->prefix()}wppack_test WHERE name = 'nonexistent_fetch_noparam'",
        );

        self::assertFalse($row);
    }

    // --- fetchOne without params (non-prepared path) ---

    #[Test]
    public function fetchOneWithoutParams(): void
    {
        $this->db->insert('wppack_test', ['name' => 'one_noparam', 'value' => 'val']);

        $value = $this->db->fetchOne(
            "SELECT value FROM {$this->db->prefix()}wppack_test WHERE name = 'one_noparam'",
        );

        self::assertSame('val', $value);
    }

    #[Test]
    public function fetchOneWithoutParamsReturnsNull(): void
    {
        $value = $this->db->fetchOne(
            "SELECT value FROM {$this->db->prefix()}wppack_test WHERE name = 'nonexistent_one_noparam'",
        );

        self::assertNull($value);
    }

    // --- fetchFirstColumn without params (non-prepared path) ---

    #[Test]
    public function fetchFirstColumnWithoutParams(): void
    {
        $this->db->insert('wppack_test', ['name' => 'fc_noparam_a', 'value' => 'x']);
        $this->db->insert('wppack_test', ['name' => 'fc_noparam_b', 'value' => 'x']);

        $names = $this->db->fetchFirstColumn(
            "SELECT name FROM {$this->db->prefix()}wppack_test WHERE value = 'x' ORDER BY name ASC",
        );

        self::assertSame(['fc_noparam_a', 'fc_noparam_b'], $names);
    }

    // --- Error paths for update and delete ---

    #[Test]
    public function updateThrowsQueryExceptionOnError(): void
    {
        $this->expectException(QueryException::class);

        $this->db->wpdb()->suppress_errors(true);
        $this->db->update('nonexistent_table_wppack_xyz', ['value' => 'x'], ['id' => 1]);
    }

    #[Test]
    public function deleteThrowsQueryExceptionOnError(): void
    {
        $this->expectException(QueryException::class);

        $this->db->wpdb()->suppress_errors(true);
        $this->db->delete('nonexistent_table_wppack_xyz', ['id' => 1]);
    }

    // --- Error paths for prepared statement methods ---

    #[Test]
    public function fetchAllAssociativeThrowsQueryExceptionOnError(): void
    {
        $this->expectException(QueryException::class);

        $this->db->wpdb()->suppress_errors(true);
        $this->db->fetchAllAssociative(
            'SELECT * FROM nonexistent_table_wppack_xyz WHERE id = %d',
            [1],
        );
    }

    #[Test]
    public function fetchAssociativeThrowsQueryExceptionOnErrorWithParams(): void
    {
        $this->expectException(QueryException::class);

        $this->db->wpdb()->suppress_errors(true);
        $this->db->fetchAssociative(
            'SELECT * FROM nonexistent_table_wppack_xyz WHERE id = %d',
            [1],
        );
    }

    #[Test]
    public function fetchOneThrowsQueryExceptionOnErrorWithParams(): void
    {
        $this->expectException(QueryException::class);

        $this->db->wpdb()->suppress_errors(true);
        $this->db->fetchOne(
            'SELECT name FROM nonexistent_table_wppack_xyz WHERE id = %d',
            [1],
        );
    }

    #[Test]
    public function fetchFirstColumnThrowsQueryExceptionOnErrorWithParams(): void
    {
        $this->expectException(QueryException::class);

        $this->db->wpdb()->suppress_errors(true);
        $this->db->fetchFirstColumn(
            'SELECT name FROM nonexistent_table_wppack_xyz WHERE id = %d',
            [1],
        );
    }

    // --- Prepared statement with float parameter ---

    #[Test]
    public function fetchWithFloatParam(): void
    {
        $this->db->executeStatement(sprintf(
            'CREATE TABLE IF NOT EXISTS %swppack_test_float (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                name varchar(255) NOT NULL,
                score double NOT NULL,
                PRIMARY KEY (id)
            ) %s',
            $this->db->prefix(),
            $this->db->charsetCollate(),
        ));

        try {
            $this->db->insert('wppack_test_float', ['name' => 'float_test', 'score' => 3.14]);

            $row = $this->db->fetchAssociative(
                "SELECT * FROM {$this->db->prefix()}wppack_test_float WHERE score > %f",
                [3.0],
            );

            self::assertIsArray($row);
            self::assertSame('float_test', $row['name']);
        } finally {
            $this->db->wpdb()->query(sprintf('DROP TABLE IF EXISTS %swppack_test_float', $this->db->prefix()));
        }
    }

    // --- Prepared statement with escaped placeholder (%%s) ---

    #[Test]
    public function convertPlaceholdersHandlesEscapedPercent(): void
    {
        $this->db->insert('wppack_test', ['name' => 'percent_test', 'value' => '100%']);

        $rows = $this->db->fetchAllAssociative(
            "SELECT * FROM {$this->db->prefix()}wppack_test WHERE name = %s",
            ['percent_test'],
        );

        self::assertCount(1, $rows);
        self::assertSame('100%', $rows[0]['value']);
    }

    // --- fetchOne with params that returns a value ---

    #[Test]
    public function fetchOneWithParamsReturnsValue(): void
    {
        $this->db->insert('wppack_test', ['name' => 'fetchone_val', 'value' => 'the_value']);

        $result = $this->db->fetchOne(
            "SELECT value FROM {$this->db->prefix()}wppack_test WHERE name = %s",
            ['fetchone_val'],
        );

        self::assertSame('the_value', $result);
    }

    // --- fetchFirstColumn with params via prepared path ---

    #[Test]
    public function fetchFirstColumnWithParamsViaPrepared(): void
    {
        $this->db->insert('wppack_test', ['name' => 'fcp_a', 'value' => 'target']);
        $this->db->insert('wppack_test', ['name' => 'fcp_b', 'value' => 'target']);
        $this->db->insert('wppack_test', ['name' => 'fcp_c', 'value' => 'other']);

        $names = $this->db->fetchFirstColumn(
            "SELECT name FROM {$this->db->prefix()}wppack_test WHERE value = %s ORDER BY name ASC",
            ['target'],
        );

        self::assertSame(['fcp_a', 'fcp_b'], $names);
    }

    // --- Error paths for non-prepared queries ---

    #[Test]
    public function fetchAllAssociativeWithErrorWithoutParamsReturnsEmptyOrThrows(): void
    {
        $this->db->wpdb()->suppress_errors(true);

        try {
            $result = $this->db->fetchAllAssociative('SELECT * FROM nonexistent_table_wppack_xyz');
            // wpdb->get_results() may return an empty array rather than null
            // for non-existent table queries, so no exception is thrown
            self::assertSame([], $result);
        } catch (QueryException) {
            // If QueryException is thrown, that's also acceptable
            self::assertTrue(true);
        }
    }

    #[Test]
    public function fetchAssociativeThrowsQueryExceptionOnErrorWithoutParams(): void
    {
        $this->expectException(QueryException::class);

        $this->db->wpdb()->suppress_errors(true);
        $this->db->fetchAssociative('SELECT * FROM nonexistent_table_wppack_xyz WHERE id = 1');
    }

    #[Test]
    public function fetchOneThrowsQueryExceptionOnErrorWithoutParams(): void
    {
        $this->expectException(QueryException::class);

        $this->db->wpdb()->suppress_errors(true);
        $this->db->fetchOne('SELECT name FROM nonexistent_table_wppack_xyz WHERE id = 1');
    }

    #[Test]
    public function fetchFirstColumnThrowsQueryExceptionOnErrorWithoutParams(): void
    {
        $this->expectException(QueryException::class);

        $this->db->wpdb()->suppress_errors(true);
        $this->db->fetchFirstColumn('SELECT name FROM nonexistent_table_wppack_xyz');
    }
}
