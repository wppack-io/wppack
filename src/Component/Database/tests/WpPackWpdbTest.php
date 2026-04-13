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
use Psr\Log\LoggerInterface;
use WpPack\Component\Database\Bridge\Sqlite\SqliteDriver;
use WpPack\Component\Database\Driver\DriverInterface;
use WpPack\Component\Database\Platform\MysqlPlatform;
use WpPack\Component\Database\Result;
use WpPack\Component\Database\Translator\NullQueryTranslator;
use WpPack\Component\Database\WpPackWpdb;

final class WpPackWpdbTest extends TestCase
{
    private WpPackWpdb $wpdb;
    private SqliteDriver $driver;

    protected function setUp(): void
    {
        $this->driver = new SqliteDriver(':memory:');
        $this->driver->connect();
        $this->driver->executeStatement(
            'CREATE TABLE wptests_posts (ID INTEGER PRIMARY KEY AUTOINCREMENT, post_title TEXT, post_status TEXT)',
        );

        $GLOBALS['table_prefix'] = 'wptests_';

        $this->wpdb = new WpPackWpdb(
            writer: $this->driver,
            translator: new NullQueryTranslator(),
            dbname: 'test',
        );
    }

    protected function tearDown(): void
    {
        $this->driver->close();
    }

    // ── prepare() ──

    #[Test]
    public function prepareConvertsPlaceholdersToQuestionMarks(): void
    {
        $sql = $this->wpdb->prepare('SELECT * FROM posts WHERE id = %d AND status = %s', 1, 'publish');

        self::assertStringContainsString('?', $sql);
        self::assertStringNotContainsString('%d', $sql);
        self::assertStringNotContainsString('%s', $sql);
    }

    #[Test]
    public function preparePreservesLiteralPercent(): void
    {
        $sql = $this->wpdb->prepare("SELECT * FROM posts WHERE title LIKE '%%test%%' AND id = %d", 1);

        self::assertStringContainsString('%test%', $sql);
        self::assertStringContainsString('?', $sql);
    }

    #[Test]
    public function prepareHandlesFloatPlaceholder(): void
    {
        $sql = $this->wpdb->prepare('SELECT * FROM posts WHERE score > %f', 3.14);

        self::assertStringContainsString('?', $sql);
        self::assertStringNotContainsString('%f', $sql);
    }

    // ── prepare() + query() prepared statement ──

    #[Test]
    public function prepareAndQueryUsePreparedStatement(): void
    {
        $this->wpdb->query("INSERT INTO wptests_posts (post_title, post_status) VALUES ('Hello', 'publish')");

        $sql = $this->wpdb->prepare('SELECT * FROM wptests_posts WHERE post_status = %s', 'publish');
        $this->wpdb->query($sql);

        self::assertSame(1, $this->wpdb->num_rows);
        self::assertSame('Hello', $this->wpdb->last_result[0]->post_title);
    }

    #[Test]
    public function preparedParamsAreUsedOnlyWhenSqlMatches(): void
    {
        $this->wpdb->query("INSERT INTO wptests_posts (post_title, post_status) VALUES ('Test', 'draft')");

        // prepare() stores params
        $this->wpdb->prepare('SELECT * FROM wptests_posts WHERE post_status = %s', 'publish');

        // But query() is called with a DIFFERENT SQL — params should NOT be used
        $this->wpdb->query('SELECT * FROM wptests_posts');

        // Should return all rows (not filtered by 'publish')
        self::assertSame(1, $this->wpdb->num_rows);
        self::assertSame('Test', $this->wpdb->last_result[0]->post_title);
    }

    #[Test]
    public function preparedParamsClearedAfterQuery(): void
    {
        $this->wpdb->query("INSERT INTO wptests_posts (post_title, post_status) VALUES ('A', 'publish')");
        $this->wpdb->query("INSERT INTO wptests_posts (post_title, post_status) VALUES ('B', 'draft')");

        // First query with params
        $sql = $this->wpdb->prepare('SELECT * FROM wptests_posts WHERE post_status = %s', 'publish');
        $this->wpdb->query($sql);
        self::assertSame(1, $this->wpdb->num_rows);

        // Second query without prepare — params should be null
        $this->wpdb->query('SELECT * FROM wptests_posts');
        self::assertSame(2, $this->wpdb->num_rows);
    }

    // ── Direct query (no prepare) ──

    #[Test]
    public function queryWithoutPrepare(): void
    {
        $this->wpdb->query("INSERT INTO wptests_posts (post_title, post_status) VALUES ('Direct', 'publish')");
        $this->wpdb->query('SELECT * FROM wptests_posts');

        self::assertSame(1, $this->wpdb->num_rows);
    }

    #[Test]
    public function queryReturnsAffectedRows(): void
    {
        $this->wpdb->query("INSERT INTO wptests_posts (post_title, post_status) VALUES ('A', 'draft')");
        $this->wpdb->query("INSERT INTO wptests_posts (post_title, post_status) VALUES ('B', 'draft')");

        $result = $this->wpdb->query("UPDATE wptests_posts SET post_status = 'publish' WHERE post_status = 'draft'");

        self::assertSame(2, $result);
    }

    // ── insert() / update() / delete() direct prepared statements ──

    #[Test]
    public function insertUsesDirectPreparedStatement(): void
    {
        $result = $this->wpdb->insert('posts', [
            'post_title' => 'Inserted',
            'post_status' => 'publish',
        ]);

        self::assertSame(1, $result);
        self::assertSame(1, $this->wpdb->insert_id);

        $this->wpdb->query('SELECT * FROM wptests_posts WHERE post_title = ?');
        // Without params this returns all — use prepare
        $sql = $this->wpdb->prepare('SELECT * FROM wptests_posts WHERE post_title = %s', 'Inserted');
        $this->wpdb->query($sql);
        self::assertSame(1, $this->wpdb->num_rows);
    }

    #[Test]
    public function updateUsesDirectPreparedStatement(): void
    {
        $this->wpdb->insert('posts', ['post_title' => 'Old', 'post_status' => 'draft']);

        $result = $this->wpdb->update(
            'posts',
            ['post_title' => 'New'],
            ['post_status' => 'draft'],
        );

        self::assertSame(1, $result);

        $sql = $this->wpdb->prepare('SELECT post_title FROM wptests_posts WHERE post_status = %s', 'draft');
        $this->wpdb->query($sql);
        self::assertSame('New', $this->wpdb->last_result[0]->post_title);
    }

    #[Test]
    public function deleteUsesDirectPreparedStatement(): void
    {
        $this->wpdb->insert('posts', ['post_title' => 'ToDelete', 'post_status' => 'trash']);

        $result = $this->wpdb->delete('posts', ['post_status' => 'trash']);

        self::assertSame(1, $result);

        $this->wpdb->query('SELECT COUNT(*) AS cnt FROM wptests_posts');
        self::assertSame(0, (int) $this->wpdb->last_result[0]->cnt);
    }

    // ── Reader/Writer split ──

    #[Test]
    public function selectUsesReaderDriver(): void
    {
        $writerDriver = $this->createMock(DriverInterface::class);
        $writerDriver->method('getPlatform')->willReturn(new MysqlPlatform());
        $writerDriver->method('getQueryTranslator')->willReturn(new NullQueryTranslator());
        $writerDriver->expects(self::never())->method('executeQuery');

        $readerDriver = $this->createMock(DriverInterface::class);
        $readerDriver->method('executeQuery')
            ->willReturn(new Result([['id' => 1]]));
        $readerDriver->method('lastInsertId')->willReturn(0);

        $wpdb = new WpPackWpdb(
            writer: $writerDriver,
            translator: new NullQueryTranslator(),
            dbname: 'test',
            reader: $readerDriver,
        );

        $wpdb->query('SELECT * FROM posts');

        self::assertSame(1, $wpdb->num_rows);
    }

    #[Test]
    public function insertUsesWriterDriver(): void
    {
        $writerDriver = $this->createMock(DriverInterface::class);
        $writerDriver->method('getPlatform')->willReturn(new MysqlPlatform());
        $writerDriver->method('getQueryTranslator')->willReturn(new NullQueryTranslator());
        $writerDriver->expects(self::once())->method('executeStatement')
            ->willReturn(1);
        $writerDriver->method('lastInsertId')->willReturn(1);

        $readerDriver = $this->createMock(DriverInterface::class);
        $readerDriver->expects(self::never())->method('executeStatement');
        $readerDriver->expects(self::never())->method('executeQuery');

        $wpdb = new WpPackWpdb(
            writer: $writerDriver,
            translator: new NullQueryTranslator(),
            dbname: 'test',
            reader: $readerDriver,
        );

        $wpdb->query("INSERT INTO posts (title) VALUES ('test')");
    }

    #[Test]
    public function allQueriesUseWriterWhenNoReader(): void
    {
        $writerDriver = $this->createMock(DriverInterface::class);
        $writerDriver->method('getPlatform')->willReturn(new MysqlPlatform());
        $writerDriver->method('getQueryTranslator')->willReturn(new NullQueryTranslator());
        $writerDriver->expects(self::once())->method('executeQuery')
            ->willReturn(new Result([['id' => 1]]));
        $writerDriver->method('lastInsertId')->willReturn(0);

        $wpdb = new WpPackWpdb(
            writer: $writerDriver,
            translator: new NullQueryTranslator(),
            dbname: 'test',
        );

        $wpdb->query('SELECT * FROM posts');
    }

    // ── Ignored queries ──

    #[Test]
    public function ignoredQueryReturnsTrue(): void
    {
        // NullQueryTranslator passes through, so SET won't be ignored.
        // Use a translator that ignores SET statements.
        $translator = $this->createMock(\WpPack\Component\Database\Translator\QueryTranslatorInterface::class);
        $translator->method('translate')->willReturn([]);

        $driver = $this->createMock(DriverInterface::class);
        $driver->method('getPlatform')->willReturn(new MysqlPlatform());
        $driver->expects(self::never())->method('executeQuery');

        $wpdb = new WpPackWpdb(
            writer: $driver,
            translator: $translator,
            dbname: 'test',
        );

        $result = $wpdb->query('SET NAMES utf8mb4');

        self::assertTrue($result);
    }

    // ── No MySQL connection ──

    #[Test]
    public function noMysqlConnectionCreated(): void
    {
        // WpPackWpdb does not call parent::__construct()
        // so no mysqli connection is attempted
        $driver = new SqliteDriver(':memory:');

        $wpdb = new WpPackWpdb(
            writer: $driver,
            translator: new NullQueryTranslator(),
            dbname: 'test',
        );

        // dbh should be null until db_connect() is called
        // But wpdb is ready
        self::assertTrue($wpdb->check_connection());
        self::assertNotInstanceOf(\mysqli::class, $wpdb->dbh ?? null);
    }

    // ── Misc ──

    #[Test]
    public function replaceUsesDirectPreparedStatement(): void
    {
        $this->wpdb->query("CREATE TABLE IF NOT EXISTS wptests_options (option_id INTEGER PRIMARY KEY, option_name TEXT UNIQUE, option_value TEXT)");
        $this->wpdb->replace('options', ['option_name' => 'test', 'option_value' => 'value1']);
        $this->wpdb->replace('options', ['option_name' => 'test', 'option_value' => 'value2']);

        $sql = $this->wpdb->prepare('SELECT option_value FROM wptests_options WHERE option_name = %s', 'test');
        $this->wpdb->query($sql);

        self::assertSame('value2', $this->wpdb->last_result[0]->option_value);
    }

    #[Test]
    public function lastErrorIsSetOnFailure(): void
    {
        $result = $this->wpdb->query('SELECT * FROM nonexistent_table');

        self::assertFalse($result);
        self::assertNotSame('', $this->wpdb->last_error);
    }

    // ── %i identifier placeholder ──

    #[Test]
    public function prepareExpandsIdentifierPlaceholder(): void
    {
        $sql = $this->wpdb->prepare('SELECT * FROM %i WHERE id = %d', 'wptests_posts', 1);

        // %i should be expanded via quoteIdentifier (SQLite uses double quotes)
        self::assertStringContainsString('"wptests_posts"', $sql);
        // %d should be converted to ?
        self::assertStringContainsString('?', $sql);
        // %i should NOT be a ? placeholder
        self::assertStringNotContainsString('%i', $sql);
    }

    #[Test]
    public function prepareWithIdentifierAndValueParams(): void
    {
        $this->wpdb->query("INSERT INTO wptests_posts (post_title, post_status) VALUES ('Test', 'publish')");

        $sql = $this->wpdb->prepare('SELECT * FROM %i WHERE post_status = %s', 'wptests_posts', 'publish');
        $this->wpdb->query($sql);

        self::assertSame(1, $this->wpdb->num_rows);
    }

    // ── Edge cases ──

    #[Test]
    public function prepareWithEmptyStringParam(): void
    {
        $sql = $this->wpdb->prepare('SELECT * FROM wptests_posts WHERE post_title = %s', '');

        self::assertStringContainsString('?', $sql);

        // Execute with empty string param
        $this->wpdb->query($sql);
        self::assertSame(0, $this->wpdb->num_rows);
    }

    #[Test]
    public function prepareWithNullParam(): void
    {
        $sql = $this->wpdb->prepare('SELECT * FROM wptests_posts WHERE post_title = %s', null);

        self::assertStringContainsString('?', $sql);
    }

    #[Test]
    public function insertWithEmptyDataReturnsFalse(): void
    {
        $result = $this->wpdb->insert('posts', []);

        self::assertFalse($result);
    }

    #[Test]
    public function queryReturnsFalseWhenNotReady(): void
    {
        $driver = new SqliteDriver(':memory:');

        $wpdb = new WpPackWpdb(
            writer: $driver,
            translator: new NullQueryTranslator(),
            dbname: 'test',
        );

        // Force not ready
        $wpdb->ready = false;

        self::assertFalse($wpdb->query('SELECT 1'));
    }

    #[Test]
    public function prepareWithLegacyArrayArgs(): void
    {
        // WordPress legacy: prepare($query, array_of_args)
        $sql = $this->wpdb->prepare('SELECT * FROM wptests_posts WHERE id = %d AND status = %s', [1, 'publish']);

        self::assertStringContainsString('?', $sql);
        self::assertStringNotContainsString('%d', $sql);
        self::assertStringNotContainsString('%s', $sql);
    }

    // ── Logger ──

    #[Test]
    public function loggerReceivesDebugOnQuery(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('debug')
            ->with('Query executed', self::callback(function (array $context): bool {
                return isset($context['sql'], $context['time_ms'], $context['driver'])
                    && $context['driver'] === 'writer';
            }));

        $driver = new SqliteDriver(':memory:');
        $driver->connect();
        $driver->executeStatement('CREATE TABLE t (id INTEGER)');

        $wpdb = new WpPackWpdb(
            writer: $driver,
            translator: new NullQueryTranslator(),
            dbname: 'test',
            logger: $logger,
        );

        $wpdb->query('SELECT * FROM t');
    }

    #[Test]
    public function loggerReceivesErrorOnFailure(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')
            ->with('Query failed', self::callback(function (array $context): bool {
                return isset($context['sql'], $context['error']);
            }));

        $driver = new SqliteDriver(':memory:');

        $wpdb = new WpPackWpdb(
            writer: $driver,
            translator: new NullQueryTranslator(),
            dbname: 'test',
            logger: $logger,
        );

        $wpdb->query('SELECT * FROM nonexistent');
    }

    #[Test]
    public function setLoggerInjectsLater(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('debug');

        $driver = new SqliteDriver(':memory:');
        $driver->connect();
        $driver->executeStatement('CREATE TABLE t (id INTEGER)');

        $wpdb = new WpPackWpdb(
            writer: $driver,
            translator: new NullQueryTranslator(),
            dbname: 'test',
        );

        // Logger injected after construction
        $wpdb->setLogger($logger);
        $wpdb->query('SELECT * FROM t');
    }
}
