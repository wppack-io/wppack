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

namespace WPPack\Component\DatabaseExport\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\DatabaseExport\Exception\ExceptionInterface;
use WPPack\Component\DatabaseExport\Exception\ExportException;
use WPPack\Component\DatabaseExport\ExportConfiguration;

#[CoversClass(ExportConfiguration::class)]
#[CoversClass(ExportException::class)]
final class ExportConfigurationTest extends TestCase
{
    #[Test]
    public function defaultsAreConservative(): void
    {
        $config = new ExportConfiguration();

        self::assertSame('', $config->dbPrefix);
        self::assertSame('WPPACK_PREFIX_', $config->tablePrefix);
        self::assertSame([], $config->excludeTables);
        self::assertSame([], $config->includeTables);
        self::assertSame([], $config->additionalPrefixes);
        self::assertSame([], $config->blogIds);
        self::assertSame(1000, $config->batchSize);
        self::assertSame(1000, $config->transactionSize);
        self::assertTrue($config->resetActivePlugins);
        self::assertTrue($config->resetTheme);
        self::assertTrue($config->replacePrefixInValues);
    }

    #[Test]
    public function defaultExcludePrefixesAreTransientAndSessionOnly(): void
    {
        $config = new ExportConfiguration();

        self::assertSame(
            ['_transient_', '_site_transient_', '_wc_session_'],
            $config->excludeOptionPrefixes,
        );
    }

    #[Test]
    public function defaultExcludeUserMetaKeysCoverSessionTokens(): void
    {
        $config = new ExportConfiguration();

        self::assertContains('session_tokens', $config->excludeUserMetaKeys);
    }

    #[Test]
    public function customFieldsAreRespected(): void
    {
        $config = new ExportConfiguration(
            dbPrefix: 'wp_',
            tablePrefix: 'EXPORT_',
            excludeTables: ['commentmeta'],
            includeTables: ['posts', 'postmeta'],
            additionalPrefixes: ['wbk_'],
            blogIds: [1, 2],
            batchSize: 500,
            transactionSize: 250,
            excludeOptionPrefixes: [],
            excludeUserMetaKeys: [],
            resetActivePlugins: false,
            resetTheme: false,
            replacePrefixInValues: false,
        );

        self::assertSame('wp_', $config->dbPrefix);
        self::assertSame('EXPORT_', $config->tablePrefix);
        self::assertSame(['commentmeta'], $config->excludeTables);
        self::assertSame(['posts', 'postmeta'], $config->includeTables);
        self::assertSame(['wbk_'], $config->additionalPrefixes);
        self::assertSame([1, 2], $config->blogIds);
        self::assertSame(500, $config->batchSize);
        self::assertSame(250, $config->transactionSize);
        self::assertSame([], $config->excludeOptionPrefixes);
        self::assertSame([], $config->excludeUserMetaKeys);
        self::assertFalse($config->resetActivePlugins);
        self::assertFalse($config->resetTheme);
        self::assertFalse($config->replacePrefixInValues);
    }

    #[Test]
    public function exportExceptionHasExpectedHierarchy(): void
    {
        $e = new ExportException('boom');

        self::assertInstanceOf(\RuntimeException::class, $e);
        self::assertInstanceOf(ExceptionInterface::class, $e);
        self::assertSame('boom', $e->getMessage());
    }
}
