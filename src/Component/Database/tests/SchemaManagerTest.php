<?php

declare(strict_types=1);

namespace WpPack\Component\Database\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Database\DatabaseManager;
use WpPack\Component\Database\SchemaManager;
use WpPack\Component\Database\TableInterface;

#[CoversClass(SchemaManager::class)]
final class SchemaManagerTest extends TestCase
{
    private ?DatabaseManager $db = null;

    protected function setUp(): void
    {
        $this->db = new DatabaseManager();
    }

    protected function tearDown(): void
    {
        if ($this->db !== null) {
            $this->db->wpdb()->query(sprintf('DROP TABLE IF EXISTS %swppack_schema_test', $this->db->prefix()));
            $this->db->wpdb()->query(sprintf('DROP TABLE IF EXISTS %swppack_schema_test2', $this->db->prefix()));
        }
    }

    #[Test]
    public function updateTableCreatesNewTable(): void
    {
        $table = $this->createTableDefinition('wppack_schema_test');
        $manager = new SchemaManager($this->db, []);

        $manager->updateTable($table);

        $result = $this->db->wpdb()->get_var(
            sprintf("SHOW TABLES LIKE '%swppack_schema_test'", $this->db->prefix()),
        );

        self::assertNotNull($result);
    }

    #[Test]
    public function updateTableReturnsDbDeltaResults(): void
    {
        $table = $this->createTableDefinition('wppack_schema_test');
        $manager = new SchemaManager($this->db, []);

        $results = $manager->updateTable($table);

        self::assertIsArray($results);
        self::assertNotEmpty($results);
    }

    #[Test]
    public function updateTableIsIdempotent(): void
    {
        $table = $this->createTableDefinition('wppack_schema_test');
        $manager = new SchemaManager($this->db, []);

        $manager->updateTable($table);
        $results = $manager->updateTable($table);

        self::assertIsArray($results);
    }

    #[Test]
    public function updateSchemaWithNoTablesReturnsEmptyArray(): void
    {
        $manager = new SchemaManager($this->db, []);

        self::assertSame([], $manager->updateSchema());
    }

    #[Test]
    public function updateSchemaCreatesAllRegisteredTables(): void
    {
        $table1 = $this->createTableDefinition('wppack_schema_test');
        $table2 = $this->createTableDefinition('wppack_schema_test2');
        $manager = new SchemaManager($this->db, [$table1, $table2]);

        $manager->updateSchema();

        $result1 = $this->db->wpdb()->get_var(
            sprintf("SHOW TABLES LIKE '%swppack_schema_test'", $this->db->prefix()),
        );
        $result2 = $this->db->wpdb()->get_var(
            sprintf("SHOW TABLES LIKE '%swppack_schema_test2'", $this->db->prefix()),
        );

        self::assertNotNull($result1);
        self::assertNotNull($result2);
    }

    #[Test]
    public function updateSchemaReturnsAggregatedResults(): void
    {
        $table1 = $this->createTableDefinition('wppack_schema_test');
        $table2 = $this->createTableDefinition('wppack_schema_test2');
        $manager = new SchemaManager($this->db, [$table1, $table2]);

        $results = $manager->updateSchema();

        self::assertIsArray($results);
        self::assertNotEmpty($results);
    }

    #[Test]
    public function getSchemasReturnsEmptyArrayWhenNoTables(): void
    {
        $manager = new SchemaManager($this->db, []);

        self::assertSame([], $manager->getSchemas());
    }

    #[Test]
    public function getSchemasReturnsAllSchemaStrings(): void
    {
        $table1 = $this->createTableDefinition('wppack_schema_test');
        $table2 = $this->createTableDefinition('wppack_schema_test2');
        $manager = new SchemaManager($this->db, [$table1, $table2]);

        $schemas = $manager->getSchemas();

        self::assertCount(2, $schemas);
        self::assertStringContainsString('wppack_schema_test', $schemas[0]);
        self::assertStringContainsString('wppack_schema_test2', $schemas[1]);
    }

    #[Test]
    public function getSchemasDoesNotExecuteDbDelta(): void
    {
        $table = $this->createTableDefinition('wppack_schema_test');
        $manager = new SchemaManager($this->db, [$table]);

        $manager->getSchemas();

        $result = $this->db->wpdb()->get_var(
            sprintf("SHOW TABLES LIKE '%swppack_schema_test'", $this->db->prefix()),
        );

        self::assertNull($result);
    }

    private function createTableDefinition(string $tableName): TableInterface
    {
        return new class ($tableName) implements TableInterface {
            public function __construct(private readonly string $tableName) {}

            public function schema(DatabaseManager $db): string
            {
                return sprintf(
                    'CREATE TABLE %s%s (
                        id bigint(20) NOT NULL AUTO_INCREMENT,
                        title varchar(255) NOT NULL,
                        PRIMARY KEY (id)
                    ) %s',
                    $db->prefix(),
                    $this->tableName,
                    $db->charsetCollate(),
                );
            }
        };
    }
}
