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

namespace WpPack\Component\Database\Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Database\Connection;
use WpPack\Component\Database\DatabaseManager;

/**
 * Tests DatabaseManager's Connection auto-creation and query delegation.
 */
final class DatabaseManagerConnectionTest extends TestCase
{
    private DatabaseManager $db;

    protected function setUp(): void
    {
        $this->db = new DatabaseManager();
    }

    #[Test]
    public function connectionIsAlwaysAvailable(): void
    {
        self::assertInstanceOf(Connection::class, $this->db->getConnection());
    }

    #[Test]
    public function fetchAllAssociativeWorks(): void
    {
        // Uses the real MySQL connection from WordPress test environment
        $results = $this->db->fetchAllAssociative('SELECT 1 AS val');

        self::assertCount(1, $results);
        self::assertSame('1', $results[0]['val']);
    }

    #[Test]
    public function fetchAssociativeWorks(): void
    {
        $row = $this->db->fetchAssociative('SELECT 1 AS val');

        self::assertNotNull($row);
        self::assertSame('1', $row['val']);
    }

    #[Test]
    public function fetchOneWorks(): void
    {
        $value = $this->db->fetchOne('SELECT 1');

        self::assertSame('1', $value);
    }

    #[Test]
    public function fetchFirstColumnWorks(): void
    {
        $values = $this->db->fetchFirstColumn('SELECT 1 UNION SELECT 2');

        self::assertSame(['1', '2'], $values);
    }

    #[Test]
    public function executeStatementWorks(): void
    {
        // CREATE and DROP to test statement execution
        $this->db->executeStatement('CREATE TEMPORARY TABLE wppack_test_dm (id INT)');
        $affected = $this->db->executeStatement('INSERT INTO wppack_test_dm VALUES (1)');

        self::assertSame(1, $affected);

        $this->db->executeStatement('DROP TEMPORARY TABLE wppack_test_dm');
    }

    #[Test]
    public function wpPlaceholdersAreConverted(): void
    {
        // %s/%d should be auto-converted to ? by toNativePlaceholders
        $results = $this->db->fetchAllAssociative(
            'SELECT %d AS val',
            [42],
        );

        self::assertSame(42, $results[0]['val']);
    }
}
