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
        if (!function_exists('wp_remote_request')) {
            self::markTestSkipped('WordPress functions are not available.');
        }

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

        self::assertSame('1', $count);
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
}
