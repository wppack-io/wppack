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

namespace WPPack\Component\Database\Schema;

/**
 * Normalizes CREATE TABLE DDL statements to portable MySQL-compatible SQL.
 *
 * Strips database-engine-specific syntax (MariaDB extensions, SQLite quirks,
 * etc.) so that the resulting DDL can be imported into any MySQL-compatible
 * database.
 */
final class DdlNormalizer
{
    public function normalize(string $ddl, string $engine): string
    {
        if ($engine === 'sqlite') {
            $ddl = $this->normalizeQuotes($ddl);
        }

        $ddl = $this->stripComments($ddl);
        $ddl = $this->stripForeignKeys($ddl);

        if ($engine === 'sqlite') {
            $ddl = $this->stripConflictClauses($ddl);
        }

        $ddl = $this->normalizeMariadbColumnTypes($ddl);

        if ($engine === 'sqlite') {
            $ddl = $this->normalizeColumnOptions($ddl);
            $ddl = $this->stripDefaults($ddl);
        }

        return $this->normalizeTableOptions($ddl);
    }

    /**
     * Replace double-quoted identifiers with backtick-quoted identifiers.
     */
    private function normalizeQuotes(string $ddl): string
    {
        return str_replace('"', '`', $ddl);
    }

    /**
     * Remove block comments.
     */
    private function stripComments(string $ddl): string
    {
        return (string) preg_replace('/\/\*.*?\*\//s', '', $ddl);
    }

    /**
     * Remove CONSTRAINT ... REFERENCES clauses (foreign keys).
     */
    private function stripForeignKeys(string $ddl): string
    {
        // CONSTRAINT at the start of a column definition (followed by comma)
        $ddl = (string) preg_replace('/\s+CONSTRAINT\b.+?REFERENCES\b.+?,/is', ',', $ddl);

        // CONSTRAINT at the end of column definitions (preceded by comma)
        $ddl = (string) preg_replace('/,\s+CONSTRAINT\b.+?REFERENCES\b[^,)]+/is', '', $ddl);

        return $ddl;
    }

    /**
     * Remove SQLite ON CONFLICT and COLLATE clauses.
     */
    private function stripConflictClauses(string $ddl): string
    {
        $ddl = (string) preg_replace('/\s+ON\s+CONFLICT\s+(?:ROLLBACK|ABORT|FAIL|IGNORE|REPLACE)/i', '', $ddl);
        $ddl = (string) preg_replace('/\s+COLLATE\s+(?:BINARY|NOCASE|RTRIM)/i', '', $ddl);

        return $ddl;
    }

    /**
     * Convert MariaDB-specific column types to MySQL-compatible equivalents.
     */
    private function normalizeMariadbColumnTypes(string $ddl): string
    {
        // Negative lookbehind prevents matching backtick-quoted column names.
        // Negative lookahead prevents matching function calls like UUID().
        $mappings = [
            '/(?<!`)\bINET4\b(?!\s*\()/i' => 'VARCHAR(15)',
            '/(?<!`)\bINET6\b(?!\s*\()/i' => 'VARCHAR(45)',
            '/(?<!`)\bUUID\b(?!\s*\()/i' => 'CHAR(36)',
            '/(?<!`)\bXMLTYPE\b(?!\s*\()/i' => 'LONGTEXT',
            '/(?<!`)\bVECTOR\s*\(\s*\d+\s*\)/i' => 'BLOB',
        ];

        foreach ($mappings as $pattern => $replacement) {
            $ddl = (string) preg_replace($pattern, $replacement, $ddl);
        }

        return $ddl;
    }

    /**
     * Normalize column options (SQLite → MySQL).
     */
    private function normalizeColumnOptions(string $ddl): string
    {
        return (string) preg_replace('/\bAUTOINCREMENT\b/i', 'AUTO_INCREMENT', $ddl);
    }

    /**
     * Normalize table engine and option declarations.
     */
    private function normalizeTableOptions(string $ddl): string
    {
        // TYPE= → ENGINE=
        $ddl = (string) preg_replace('/\bTYPE\s*=\s*(\w+)/i', 'ENGINE=$1', $ddl);

        // Non-InnoDB engines → InnoDB
        $unsupported = ['Aria', 'S3', 'ColumnStore', 'Spider', 'CONNECT', 'Mroonga'];
        foreach ($unsupported as $eng) {
            $ddl = (string) preg_replace('/\bENGINE\s*=\s*' . $eng . '\b/i', 'ENGINE=InnoDB', $ddl);
        }

        // Strip MariaDB-specific table options
        $stripPatterns = [
            '/\s*TRANSACTIONAL\s*=\s*\w+/i',
            '/\s*PAGE_CHECKSUM\s*=\s*\w+/i',
            '/\s*TABLE_CHECKSUM\s*=\s*\w+/i',
            '/\s*ROW_FORMAT\s*=\s*\w+/i',
            '/\s*PAGE_COMPRESSED\s*=\s*\w+/i',
            '/\s*PAGE_COMPRESSION_LEVEL\s*=\s*\w+/i',
            '/\s*ENCRYPTED\s*=\s*\w+/i',
            '/\s*ENCRYPTION_KEY_ID\s*=\s*\w+/i',
            '/\s*(?:WITH|WITHOUT)\s+SYSTEM\s+VERSIONING/i',
        ];

        foreach ($stripPatterns as $pattern) {
            $ddl = (string) preg_replace($pattern, '', $ddl);
        }

        return $ddl;
    }

    /**
     * Remove DEFAULT clauses (SQLite → MySQL).
     */
    private function stripDefaults(string $ddl): string
    {
        $ddl = (string) preg_replace('/\bDEFAULT\s+\d+/i', '', $ddl);
        $ddl = (string) preg_replace("/\bDEFAULT\s+'[^']*'/i", '', $ddl);

        return $ddl;
    }
}
