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

namespace WPPack\Component\DatabaseExport\Writer;

use WPPack\Component\Database\Schema\TableSchema;
use WPPack\Component\DatabaseExport\ExportConfiguration;

interface ExportWriterInterface
{
    /**
     * @param resource $stream
     */
    public function begin($stream, ExportConfiguration $config): void;

    /**
     * @param resource $stream
     */
    public function beginTable($stream, TableSchema $schema): void;

    /**
     * @param resource              $stream
     * @param list<array<string, mixed>> $rows
     */
    public function writeRows($stream, TableSchema $schema, array $rows): void;

    /**
     * @param resource $stream
     */
    public function endTable($stream, TableSchema $schema): void;

    /**
     * @param resource $stream
     */
    public function end($stream): void;
}
