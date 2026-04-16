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

namespace WpPack\Component\Database\Tests\Schema;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WpPack\Component\Database\Schema\DdlNormalizer;

final class DdlNormalizerTest extends TestCase
{
    private DdlNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new DdlNormalizer();
    }

    // ── MySQL passthrough ──

    #[Test]
    public function mysqlDdlPassesThroughUnchanged(): void
    {
        $ddl = 'CREATE TABLE `wp_posts` (`ID` bigint(20) unsigned NOT NULL AUTO_INCREMENT, `post_title` text NOT NULL) ENGINE=InnoDB';

        $result = $this->normalizer->normalize($ddl, 'mysql');

        self::assertSame($ddl, $result);
    }

    // ── SQLite normalizations ──

    #[Test]
    public function sqliteDoubleQuotesToBackticks(): void
    {
        $ddl = 'CREATE TABLE "wp_posts" ("ID" INTEGER PRIMARY KEY AUTOINCREMENT)';

        $result = $this->normalizer->normalize($ddl, 'sqlite');

        self::assertStringContainsString('`wp_posts`', $result);
        self::assertStringContainsString('`ID`', $result);
        self::assertStringNotContainsString('"', $result);
    }

    #[Test]
    public function sqliteAutoIncrementNormalized(): void
    {
        $ddl = 'CREATE TABLE "wp_posts" ("ID" INTEGER PRIMARY KEY AUTOINCREMENT)';

        $result = $this->normalizer->normalize($ddl, 'sqlite');

        self::assertStringContainsString('AUTO_INCREMENT', $result);
        self::assertStringNotContainsString('AUTOINCREMENT', $result);
    }

    #[Test]
    public function sqliteConflictClausesRemoved(): void
    {
        $ddl = 'CREATE TABLE "wp_options" ("option_id" INTEGER PRIMARY KEY ON CONFLICT REPLACE, "option_name" TEXT COLLATE NOCASE)';

        $result = $this->normalizer->normalize($ddl, 'sqlite');

        self::assertStringNotContainsString('ON CONFLICT REPLACE', $result);
        self::assertStringNotContainsString('COLLATE NOCASE', $result);
    }

    #[Test]
    public function sqliteDefaultsStripped(): void
    {
        $ddl = "CREATE TABLE \"wp_posts\" (\"post_status\" TEXT DEFAULT 'publish', \"comment_count\" INTEGER DEFAULT 0)";

        $result = $this->normalizer->normalize($ddl, 'sqlite');

        self::assertStringNotContainsString("DEFAULT 'publish'", $result);
        self::assertStringNotContainsString('DEFAULT 0', $result);
    }

    #[Test]
    public function sqliteFullNormalization(): void
    {
        $ddl = <<<'SQL'
CREATE TABLE "wp_options" (
  "option_id" INTEGER PRIMARY KEY AUTOINCREMENT,
  "option_name" TEXT NOT NULL DEFAULT '' COLLATE NOCASE,
  "option_value" TEXT NOT NULL DEFAULT '',
  "autoload" TEXT NOT NULL DEFAULT 'yes' ON CONFLICT IGNORE
)
SQL;

        $result = $this->normalizer->normalize($ddl, 'sqlite');

        self::assertStringContainsString('`option_id`', $result);
        self::assertStringContainsString('AUTO_INCREMENT', $result);
        self::assertStringNotContainsString('AUTOINCREMENT', $result);
        self::assertStringNotContainsString('COLLATE NOCASE', $result);
        self::assertStringNotContainsString('ON CONFLICT IGNORE', $result);
        self::assertStringNotContainsString("DEFAULT ''", $result);
        self::assertStringNotContainsString("DEFAULT 'yes'", $result);
    }

    // ── Block comments ──

    #[Test]
    public function blockCommentsStripped(): void
    {
        $ddl = 'CREATE TABLE `wp_posts` (`ID` bigint(20) /* primary key */ NOT NULL) ENGINE=InnoDB /* comment */';

        $result = $this->normalizer->normalize($ddl, 'mysql');

        self::assertStringNotContainsString('/*', $result);
        self::assertStringNotContainsString('primary key', $result);
        self::assertStringContainsString('`ID` bigint(20)', $result);
    }

    #[Test]
    public function multilineBlockCommentsStripped(): void
    {
        $ddl = "CREATE TABLE `t` (`id` INT /* multi\nline\ncomment */ NOT NULL)";

        $result = $this->normalizer->normalize($ddl, 'mysql');

        self::assertStringNotContainsString('/*', $result);
        self::assertStringContainsString('`id` INT', $result);
    }

    // ── Foreign keys ──

    #[Test]
    public function foreignKeyConstraintAtStartRemoved(): void
    {
        $ddl = "CREATE TABLE `t` (\n  `id` INT NOT NULL,\n  CONSTRAINT `fk_test` FOREIGN KEY (`parent_id`) REFERENCES `parent` (`id`),\n  `name` VARCHAR(255)\n)";

        $result = $this->normalizer->normalize($ddl, 'mysql');

        self::assertStringNotContainsString('CONSTRAINT', $result);
        self::assertStringNotContainsString('REFERENCES', $result);
        self::assertStringContainsString('`id` INT NOT NULL', $result);
        self::assertStringContainsString('`name` VARCHAR(255)', $result);
    }

    #[Test]
    public function foreignKeyConstraintAtEndRemoved(): void
    {
        $ddl = "CREATE TABLE `t` (\n  `id` INT NOT NULL,\n  `name` VARCHAR(255),\n  CONSTRAINT `fk_test` FOREIGN KEY (`parent_id`) REFERENCES `parent` (`id`)\n)";

        $result = $this->normalizer->normalize($ddl, 'mysql');

        self::assertStringNotContainsString('CONSTRAINT', $result);
        self::assertStringNotContainsString('REFERENCES', $result);
    }

    // ── MariaDB column types ──

    #[Test]
    public function mariadbInet4Converted(): void
    {
        $ddl = 'CREATE TABLE `t` (`ip` INET4 NOT NULL)';

        $result = $this->normalizer->normalize($ddl, 'mysql');

        self::assertStringContainsString('VARCHAR(15)', $result);
        self::assertStringNotContainsString('INET4', $result);
    }

    #[Test]
    public function mariadbInet6Converted(): void
    {
        $ddl = 'CREATE TABLE `t` (`ip` INET6 NOT NULL)';

        $result = $this->normalizer->normalize($ddl, 'mysql');

        self::assertStringContainsString('VARCHAR(45)', $result);
    }

    #[Test]
    public function mariadbUuidConverted(): void
    {
        $ddl = 'CREATE TABLE `t` (`uuid_col` UUID NOT NULL)';

        $result = $this->normalizer->normalize($ddl, 'mysql');

        self::assertStringContainsString('CHAR(36)', $result);
        self::assertStringNotContainsString(' UUID ', $result);
    }

    #[Test]
    public function mariadbUuidColumnNameNotConverted(): void
    {
        $ddl = 'CREATE TABLE `t` (`uuid` VARCHAR(36) NOT NULL)';

        $result = $this->normalizer->normalize($ddl, 'mysql');

        // Backtick-quoted `uuid` should not be converted
        self::assertStringContainsString('`uuid` VARCHAR(36)', $result);
    }

    #[Test]
    public function mariadbXmltypeConverted(): void
    {
        $ddl = 'CREATE TABLE `t` (`data` XMLTYPE NOT NULL)';

        $result = $this->normalizer->normalize($ddl, 'mysql');

        self::assertStringContainsString('LONGTEXT', $result);
    }

    #[Test]
    public function mariadbVectorConverted(): void
    {
        $ddl = 'CREATE TABLE `t` (`embedding` VECTOR(768) NOT NULL)';

        $result = $this->normalizer->normalize($ddl, 'mysql');

        self::assertStringContainsString('BLOB', $result);
        self::assertStringNotContainsString('VECTOR', $result);
    }

    #[Test]
    public function uuidFunctionCallNotConverted(): void
    {
        $ddl = "CREATE TABLE `t` (`id` CHAR(36) DEFAULT (UUID()))";

        $result = $this->normalizer->normalize($ddl, 'mysql');

        // UUID() function call should not be converted
        self::assertStringContainsString('UUID()', $result);
    }

    // ── Table options ──

    #[Test]
    public function typeConvertedToEngine(): void
    {
        $ddl = 'CREATE TABLE `t` (`id` INT) TYPE=InnoDB';

        $result = $this->normalizer->normalize($ddl, 'mysql');

        self::assertStringContainsString('ENGINE=InnoDB', $result);
        self::assertStringNotContainsString('TYPE=', $result);
    }

    #[Test]
    public function ariaEngineConvertedToInnodb(): void
    {
        $ddl = 'CREATE TABLE `t` (`id` INT) ENGINE=Aria';

        $result = $this->normalizer->normalize($ddl, 'mysql');

        self::assertStringContainsString('ENGINE=InnoDB', $result);
        self::assertStringNotContainsString('Aria', $result);
    }

    #[Test]
    public function unsupportedEnginesConvertedToInnodb(): void
    {
        foreach (['S3', 'ColumnStore', 'Spider', 'CONNECT', 'Mroonga'] as $engine) {
            $ddl = "CREATE TABLE `t` (`id` INT) ENGINE={$engine}";

            $result = $this->normalizer->normalize($ddl, 'mysql');

            self::assertStringContainsString('ENGINE=InnoDB', $result, "ENGINE={$engine} should be converted");
        }
    }

    #[Test]
    public function mariadbTableOptionsStripped(): void
    {
        $ddl = 'CREATE TABLE `t` (`id` INT) ENGINE=InnoDB TRANSACTIONAL=1 PAGE_CHECKSUM=1 TABLE_CHECKSUM=1 ROW_FORMAT=DYNAMIC';

        $result = $this->normalizer->normalize($ddl, 'mysql');

        self::assertStringContainsString('ENGINE=InnoDB', $result);
        self::assertStringNotContainsString('TRANSACTIONAL', $result);
        self::assertStringNotContainsString('PAGE_CHECKSUM', $result);
        self::assertStringNotContainsString('TABLE_CHECKSUM', $result);
        self::assertStringNotContainsString('ROW_FORMAT', $result);
    }

    #[Test]
    public function mariadbCompressionAndEncryptionStripped(): void
    {
        $ddl = 'CREATE TABLE `t` (`id` INT) ENGINE=InnoDB PAGE_COMPRESSED=1 PAGE_COMPRESSION_LEVEL=9 ENCRYPTED=YES ENCRYPTION_KEY_ID=1';

        $result = $this->normalizer->normalize($ddl, 'mysql');

        self::assertStringNotContainsString('PAGE_COMPRESSED', $result);
        self::assertStringNotContainsString('PAGE_COMPRESSION_LEVEL', $result);
        self::assertStringNotContainsString('ENCRYPTED', $result);
        self::assertStringNotContainsString('ENCRYPTION_KEY_ID', $result);
    }

    #[Test]
    public function systemVersioningStripped(): void
    {
        $ddl = 'CREATE TABLE `t` (`id` INT) ENGINE=InnoDB WITH SYSTEM VERSIONING';

        $result = $this->normalizer->normalize($ddl, 'mysql');

        self::assertStringNotContainsString('SYSTEM VERSIONING', $result);
    }

    #[Test]
    public function withoutSystemVersioningStripped(): void
    {
        $ddl = 'CREATE TABLE `t` (`id` INT) ENGINE=InnoDB WITHOUT SYSTEM VERSIONING';

        $result = $this->normalizer->normalize($ddl, 'mysql');

        self::assertStringNotContainsString('SYSTEM VERSIONING', $result);
    }

    // ── PostgreSQL passthrough (no special handling yet) ──

    #[Test]
    public function postgresqlStripsCommentsAndForeignKeys(): void
    {
        $ddl = "CREATE TABLE `t` (\n  `id` INT NOT NULL,\n  CONSTRAINT `fk` FOREIGN KEY (`pid`) REFERENCES `p` (`id`)\n) /* comment */";

        $result = $this->normalizer->normalize($ddl, 'pgsql');

        self::assertStringNotContainsString('CONSTRAINT', $result);
        self::assertStringNotContainsString('/*', $result);
    }
}
