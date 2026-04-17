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
    private ?\wpdb $originalWpdb = null;

    protected function setUp(): void
    {
        $this->originalWpdb = $GLOBALS['wpdb'] ?? null;

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

        if ($this->originalWpdb !== null) {
            $GLOBALS['wpdb'] = $this->originalWpdb;
        }
    }

    // ── prepare() ──

    #[Test]
    public function prepareEmitsPlaceholderAndMarker(): void
    {
        $sql = $this->wpdb->prepare('SELECT * FROM posts WHERE id = %d AND status = %s', 1, 'publish');

        self::assertMatchesRegularExpression(
            '#^SELECT \* FROM posts WHERE id = \? AND status = \?/\*WPP:[a-f0-9]{12}\*/$#',
            $sql,
        );
    }

    #[Test]
    public function preparePreservesLiteralPercent(): void
    {
        $sql = $this->wpdb->prepare("SELECT * FROM posts WHERE title LIKE '%%test%%' AND id = %d", 1);

        self::assertMatchesRegularExpression(
            "#^SELECT \* FROM posts WHERE title LIKE '%test%' AND id = \?/\*WPP:[a-f0-9]{12}\*/$#",
            $sql,
        );
    }

    #[Test]
    public function prepareHandlesFloatPlaceholder(): void
    {
        $sql = $this->wpdb->prepare('SELECT * FROM posts WHERE score > %f', 3.14);

        self::assertMatchesRegularExpression(
            '#^SELECT \* FROM posts WHERE score > \?/\*WPP:[a-f0-9]{12}\*/$#',
            $sql,
        );
    }

    // ── prepare(): literal-wrap for '%s' inside '...' ──

    #[Test]
    public function prepareWrapsLikePatternWithPlaceholderAsSingleParam(): void
    {
        // `'%%%s%%'` → '%foo%'. The whole literal is replaced with a single
        // '?' so the bound value carries the LIKE wildcards. This is the only
        // engine-neutral way: splicing the value into '%?%' would be
        // interpreted by MySQL as a literal '?' byte, not a bind position.
        $sql = $this->wpdb->prepare("SELECT * FROM posts WHERE title LIKE '%%%s%%'", 'foo');

        self::assertMatchesRegularExpression(
            '#^SELECT \* FROM posts WHERE title LIKE \?/\*WPP:[a-f0-9]{12}\*/$#',
            $sql,
        );

        $this->wpdb->query($sql);
        self::assertSame(['%foo%'], $this->wpdb->last_params);
    }

    #[Test]
    public function prepareWrapsLiteralWithMixedContentAndPlaceholder(): void
    {
        // The literal has chars BEFORE and AFTER the placeholder.
        // Expect a single '?' bound to the full composite.
        $sql = $this->wpdb->prepare("SELECT * FROM users WHERE login = 'admin_%s'", 'bob');

        self::assertMatchesRegularExpression(
            '#^SELECT \* FROM users WHERE login = \?/\*WPP:[a-f0-9]{12}\*/$#',
            $sql,
        );

        $this->wpdb->query($sql);
        self::assertSame(['admin_bob'], $this->wpdb->last_params);
    }

    #[Test]
    public function prepareLeavesLiteralWithoutPlaceholderVerbatim(): void
    {
        // O''Brien uses a doubled-quote escape inside a literal. No
        // placeholder is present, so the literal is re-emitted as SQL
        // (doubled-quote form).
        $sql = $this->wpdb->prepare("SELECT * FROM users WHERE name = 'O''Brien' AND id = %d", 5);

        self::assertMatchesRegularExpression(
            "#^SELECT \* FROM users WHERE name = 'O''Brien' AND id = \?/\*WPP:[a-f0-9]{12}\*/$#",
            $sql,
        );
    }

    #[Test]
    public function prepareMixesOutsideAndInsideLiteralPlaceholders(): void
    {
        // Outside and inside the literal each become their own '?'.
        $sql = $this->wpdb->prepare(
            "SELECT * FROM t WHERE x = 'a%s' AND y = %s",
            'p',
            'q',
        );

        self::assertMatchesRegularExpression(
            '#^SELECT \* FROM t WHERE x = \? AND y = \?/\*WPP:[a-f0-9]{12}\*/$#',
            $sql,
        );

        $this->wpdb->query($sql);
        self::assertSame(['ap', 'q'], $this->wpdb->last_params);
    }

    #[Test]
    public function prepareWithEmptyLiteral(): void
    {
        // An empty '' literal has no placeholder, so it is re-emitted as '';
        // the only bound '?' comes from the outside %s.
        $sql = $this->wpdb->prepare("SELECT '' AS note, %s AS tag", 'foo');

        self::assertMatchesRegularExpression(
            "#^SELECT '' AS note, \? AS tag/\*WPP:[a-f0-9]{12}\*/$#",
            $sql,
        );

        $this->wpdb->query($sql);
        self::assertSame(['foo'], $this->wpdb->last_params);
    }

    #[Test]
    public function prepareWithBackslashEscapedQuoteAndPlaceholder(): void
    {
        // Backslash-escaped single quote inside the literal is unescaped into
        // the composite value; the %s inside the same literal triggers
        // literal-wrap. Expect one '?' bound to "a'b".
        $sql = $this->wpdb->prepare("SELECT * FROM t WHERE x = 'a\\'%s'", 'b');

        self::assertMatchesRegularExpression(
            '#^SELECT \* FROM t WHERE x = \?/\*WPP:[a-f0-9]{12}\*/$#',
            $sql,
        );

        $this->wpdb->query($sql);
        self::assertSame(["a'b"], $this->wpdb->last_params);
    }

    #[Test]
    public function prepareWithMultiplePlaceholdersInSingleLiteral(): void
    {
        // Three placeholders inside the same '...' collapse into one '?'
        // bound to the full composite string.
        $sql = $this->wpdb->prepare(
            "SELECT * FROM t WHERE label = '%s-%s-%s'",
            'x',
            'y',
            'z',
        );

        self::assertMatchesRegularExpression(
            '#^SELECT \* FROM t WHERE label = \?/\*WPP:[a-f0-9]{12}\*/$#',
            $sql,
        );

        $this->wpdb->query($sql);
        self::assertSame(['x-y-z'], $this->wpdb->last_params);
    }

    #[Test]
    public function prepareWithIntPlaceholderInsideLiteral(): void
    {
        // %d inside a literal is cast via (int) and folded into the composite
        // string — the whole literal still becomes one '?'.
        $sql = $this->wpdb->prepare("SELECT * FROM t WHERE tag = 'id=%d'", 5);

        self::assertMatchesRegularExpression(
            '#^SELECT \* FROM t WHERE tag = \?/\*WPP:[a-f0-9]{12}\*/$#',
            $sql,
        );

        $this->wpdb->query($sql);
        self::assertSame(['id=5'], $this->wpdb->last_params);
    }

    #[Test]
    public function prepareWithIdentifierInsideLiteralConsumesArg(): void
    {
        // %i inside a literal is semantic nonsense but must still consume its
        // argument — otherwise later placeholders silently shift to the wrong
        // binds. The identifier is folded into the composite value.
        $sql = $this->wpdb->prepare(
            "SELECT FROM '%i' WHERE y = %s",
            'tbl',
            'val',
        );

        self::assertMatchesRegularExpression(
            '#^SELECT FROM \? WHERE y = \?/\*WPP:[a-f0-9]{12}\*/$#',
            $sql,
        );

        $this->wpdb->query($sql);
        $params = $this->wpdb->last_params;

        self::assertCount(2, $params);
        self::assertSame('val', $params[1]);
        // The identifier was quoted by Platform::quoteIdentifier() before
        // being folded into the literal composite. We only assert it contains
        // the raw name, because the quoting style is platform-dependent
        // (backticks on MySQL, double quotes on PostgreSQL/SQLite).
        self::assertStringContainsString('tbl', $params[0]);
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
        $result = $this->wpdb->insert('wptests_posts', [
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
        $this->wpdb->insert('wptests_posts', ['post_title' => 'Old', 'post_status' => 'draft']);

        $result = $this->wpdb->update(
            'wptests_posts',
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
        $this->wpdb->insert('wptests_posts', ['post_title' => 'ToDelete', 'post_status' => 'trash']);

        $result = $this->wpdb->delete('wptests_posts', ['post_status' => 'trash']);

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
        $this->wpdb->replace('wptests_options', ['option_name' => 'test', 'option_value' => 'value1']);
        $this->wpdb->replace('wptests_options', ['option_name' => 'test', 'option_value' => 'value2']);

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

        // %i is expanded inline via quoteIdentifier (SQLite uses double quotes)
        self::assertStringContainsString('"wptests_posts"', $sql);
        // %d becomes a '?' placeholder with a trailing bank marker
        self::assertStringContainsString('= ?', $sql);
        self::assertMatchesRegularExpression('#/\*WPP:[a-f0-9]{12}\*/$#', $sql);
        // No raw placeholders should remain
        self::assertStringNotContainsString('%i', $sql);
        self::assertStringNotContainsString('%d', $sql);
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

        self::assertStringContainsString('= ?', $sql);

        // Execute with empty string param
        $this->wpdb->query($sql);
        self::assertSame(0, $this->wpdb->num_rows);
        self::assertSame([''], $this->wpdb->last_params);
    }

    #[Test]
    public function prepareWithNullParam(): void
    {
        $sql = $this->wpdb->prepare('SELECT * FROM wptests_posts WHERE post_title = %s', null);

        self::assertStringContainsString('= ?', $sql);
        // null coerces to empty string at execute time
        $this->wpdb->query($sql);
        self::assertSame([''], $this->wpdb->last_params);
    }

    #[Test]
    public function insertWithEmptyDataReturnsFalse(): void
    {
        $result = $this->wpdb->insert('wptests_posts', []);

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

        self::assertMatchesRegularExpression(
            '#^SELECT \* FROM wptests_posts WHERE id = \? AND status = \?/\*WPP:[a-f0-9]{12}\*/$#',
            $sql,
        );

        $this->wpdb->query($sql);
        self::assertSame([1, 'publish'], $this->wpdb->last_params);
    }

    // ── Reader/Writer affinity ──

    #[Test]
    public function readsUseReaderWhenNoWriteHasHappened(): void
    {
        // writer and reader point at separate :memory: databases so we can
        // assert routing by seeding each with different data.
        $writer = new SqliteDriver(':memory:');
        $writer->connect();
        $writer->executeStatement('CREATE TABLE t (tag TEXT)');
        $writer->executeStatement("INSERT INTO t (tag) VALUES ('writer')");

        $reader = new SqliteDriver(':memory:');
        $reader->connect();
        $reader->executeStatement('CREATE TABLE t (tag TEXT)');
        $reader->executeStatement("INSERT INTO t (tag) VALUES ('reader')");

        $wpdb = new WpPackWpdb(
            writer: $writer,
            translator: new NullQueryTranslator(),
            dbname: 'test',
            reader: $reader,
        );

        $value = $wpdb->get_var('SELECT tag FROM t');

        self::assertSame('reader', $value, 'Plain SELECT without a prior write must route to the reader.');
    }

    #[Test]
    public function readsStickToWriterAfterTransactionBegin(): void
    {
        $writer = new SqliteDriver(':memory:');
        $writer->connect();
        $writer->executeStatement('CREATE TABLE t (tag TEXT)');
        $writer->executeStatement("INSERT INTO t (tag) VALUES ('writer')");

        $reader = new SqliteDriver(':memory:');
        $reader->connect();
        $reader->executeStatement('CREATE TABLE t (tag TEXT)');
        $reader->executeStatement("INSERT INTO t (tag) VALUES ('reader')");

        $wpdb = new WpPackWpdb(
            writer: $writer,
            translator: new NullQueryTranslator(),
            dbname: 'test',
            reader: $reader,
        );

        $wpdb->query('BEGIN');

        $value = $wpdb->get_var('SELECT tag FROM t');

        // Inside a transaction the SELECT must hit the writer, even though
        // the default routing would send it to the reader. Otherwise a
        // transaction that reads-after-write would observe stale data.
        self::assertSame('writer', $value);

        $wpdb->query('COMMIT');
    }

    #[Test]
    public function readsStickToWriterAfterAnyInsert(): void
    {
        $writer = new SqliteDriver(':memory:');
        $writer->connect();
        $writer->executeStatement('CREATE TABLE t (tag TEXT)');
        $writer->executeStatement("INSERT INTO t (tag) VALUES ('writer')");

        $reader = new SqliteDriver(':memory:');
        $reader->connect();
        $reader->executeStatement('CREATE TABLE t (tag TEXT)');
        $reader->executeStatement("INSERT INTO t (tag) VALUES ('reader')");

        $wpdb = new WpPackWpdb(
            writer: $writer,
            translator: new NullQueryTranslator(),
            dbname: 'test',
            reader: $reader,
        );

        $wpdb->insert('t', ['tag' => 'fresh']);

        $value = $wpdb->get_var("SELECT tag FROM t WHERE tag = 'fresh'");

        // The fresh row exists only in the writer database. If routing
        // had leaked to the reader, this would be null.
        self::assertSame('fresh', $value);
    }

    #[Test]
    public function resetReaderStickinessRestoresReaderRouting(): void
    {
        $writer = new SqliteDriver(':memory:');
        $writer->connect();
        $writer->executeStatement('CREATE TABLE t (tag TEXT)');
        $writer->executeStatement("INSERT INTO t (tag) VALUES ('writer')");

        $reader = new SqliteDriver(':memory:');
        $reader->connect();
        $reader->executeStatement('CREATE TABLE t (tag TEXT)');
        $reader->executeStatement("INSERT INTO t (tag) VALUES ('reader')");

        $wpdb = new WpPackWpdb(
            writer: $writer,
            translator: new NullQueryTranslator(),
            dbname: 'test',
            reader: $reader,
        );

        $wpdb->insert('t', ['tag' => 'fresh']);
        $wpdb->resetReaderStickiness();

        // After the reset, SELECTs are reader-eligible again.
        $value = $wpdb->get_var('SELECT tag FROM t LIMIT 1');

        self::assertSame('reader', $value);
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

    // ── Legacy wpdb API compat ──

    #[Test]
    public function realEscapeDoublesEmbeddedQuotesOnSqlite(): void
    {
        // wpdb::_real_escape used to return addslashes() output, which is
        // MySQL-shaped and wrong for SQLite / PostgreSQL. It now delegates
        // to Driver::escapeStringContent(), so SQLite callers get the SQL-
        // standard doubled-quote form.
        $driver = new SqliteDriver(':memory:');
        $driver->connect();

        $wpdb = new WpPackWpdb(
            writer: $driver,
            translator: new NullQueryTranslator(),
            dbname: 'test',
        );

        self::assertSame("O''Brien", $wpdb->_real_escape("O'Brien"));
    }

    #[Test]
    public function realEscapeReturnsEmptyForNonString(): void
    {
        $wpdb = new WpPackWpdb(
            writer: new SqliteDriver(':memory:'),
            translator: new NullQueryTranslator(),
            dbname: 'test',
        );

        // @phpstan-ignore-next-line argument.type
        self::assertSame('', $wpdb->_real_escape(42));
        // @phpstan-ignore-next-line argument.type
        self::assertSame('', $wpdb->_real_escape(null));
    }

    #[Test]
    public function realEscapeProtectsEmbeddedPercentFromLaterPrepare(): void
    {
        // add_placeholder_escape must run so a caller doing
        // "INSERT ... VALUES ('" . $wpdb->_real_escape($x) . "')" and later
        // feeding that string into prepare() doesn't trip a spurious %s.
        $driver = new SqliteDriver(':memory:');
        $driver->connect();

        $wpdb = new WpPackWpdb(
            writer: $driver,
            translator: new NullQueryTranslator(),
            dbname: 'test',
        );

        $escaped = $wpdb->_real_escape('hello %s world');

        // Should not equal the raw input — placeholder escape must have
        // rewritten the percent sign. Exact form depends on wpdb internals.
        self::assertNotSame('hello %s world', $escaped);
    }

    #[Test]
    public function getVarAndGetResultsWorkOnSqlite(): void
    {
        // Standard wpdb::get_var / get_row / get_results read $last_result.
        // WpPackWpdb populates last_result as an array of stdClass from
        // the driver's associative rows, so the inherited helpers should
        // Just Work. Regression test in case we ever stop.
        $driver = new SqliteDriver(':memory:');
        $driver->connect();
        $driver->executeStatement('CREATE TABLE t (id INTEGER, name TEXT)');
        $driver->executeStatement("INSERT INTO t (id, name) VALUES (1, 'alice')");
        $driver->executeStatement("INSERT INTO t (id, name) VALUES (2, 'bob')");

        $wpdb = new WpPackWpdb(
            writer: $driver,
            translator: new NullQueryTranslator(),
            dbname: 'test',
        );

        self::assertSame('alice', $wpdb->get_var('SELECT name FROM t ORDER BY id'));

        $row = $wpdb->get_row('SELECT id, name FROM t ORDER BY id DESC');
        self::assertIsObject($row);
        self::assertSame('bob', $row->name);

        $rows = $wpdb->get_results('SELECT id, name FROM t ORDER BY id', ARRAY_A);
        self::assertIsArray($rows);
        self::assertCount(2, $rows);
        self::assertSame(['id' => 1, 'name' => 'alice'], $rows[0]);
    }

    #[Test]
    public function loggerDoesNotLeakBoundValues(): void
    {
        // PII protection: the PSR logger context must never embed raw param
        // values by default. Only a type/length summary is safe to ship to
        // external APM / log aggregators.
        $capturedContext = null;
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')
            ->willReturnCallback(function (string $message, array $context) use (&$capturedContext): void {
                $capturedContext = $context;
            });

        $driver = new SqliteDriver(':memory:');
        $driver->connect();

        $wpdb = new WpPackWpdb(
            writer: $driver,
            translator: new NullQueryTranslator(),
            dbname: 'test',
            logger: $logger,
        );

        // Failing query carrying a "password-like" value. We bind via prepare()
        // so it flows through the normal param pipeline.
        $sql = $wpdb->prepare('INSERT INTO missing_table (pw) VALUES (%s)', 'super-secret-password');
        $wpdb->query($sql);

        self::assertIsArray($capturedContext);
        self::assertArrayHasKey('params', $capturedContext);
        self::assertSame(['#0' => 'string(21)'], $capturedContext['params']);
        self::assertArrayNotHasKey('raw_params', $capturedContext);
        self::assertArrayNotHasKey('interpolated_sql', $capturedContext);

        $serialized = var_export($capturedContext, true);
        self::assertStringNotContainsString('super-secret-password', $serialized);
    }
}
