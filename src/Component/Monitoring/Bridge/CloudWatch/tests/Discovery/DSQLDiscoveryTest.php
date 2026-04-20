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

namespace WPPack\Component\Monitoring\Bridge\CloudWatch\Tests\Discovery;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WPPack\Component\Monitoring\Bridge\CloudWatch\Discovery\DSQLDiscovery;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class DSQLDiscoveryTest extends TestCase
{
    #[Test]
    public function returnsEmptyWhenNoDSQLEndpoint(): void
    {
        $discovery = new DSQLDiscovery();

        // Test environment has no DSQL endpoint
        self::assertSame([], $discovery->getProviders());
    }

    #[Test]
    public function implementsMonitoringProviderInterface(): void
    {
        $discovery = new DSQLDiscovery();

        self::assertInstanceOf(\WPPack\Component\Monitoring\MonitoringProviderInterface::class, $discovery);
    }

    #[Test]
    public function returnsProviderForDsqlDsn(): void
    {
        // CI matrix cells export DATABASE_DSN via env → wp-config defines
        // the constant during bootstrap, so our define() is a no-op there.
        // Skip when we can't control the constant value.
        if (\defined('DATABASE_DSN') && \constant('DATABASE_DSN') !== 'dsql://abc123xyz.dsql.us-east-1.on.aws/postgres') {
            self::markTestSkipped('DATABASE_DSN already defined by bootstrap; cannot override in-process.');
        }
        if (!\defined('DATABASE_DSN')) {
            \define('DATABASE_DSN', 'dsql://abc123xyz.dsql.us-east-1.on.aws/postgres');
        }

        $providers = (new DSQLDiscovery())->getProviders();

        self::assertCount(1, $providers);
        $provider = $providers[0];
        self::assertSame('dsql', $provider->id);
        self::assertSame('Aurora DSQL', $provider->label);
        self::assertSame('cloudwatch', $provider->bridge);
        self::assertSame('us-east-1', $provider->settings->region);
        self::assertCount(4, $provider->metrics);
        self::assertSame('abc123xyz', $provider->metrics[0]->dimensions['ClusterIdentifier']);
    }

    #[Test]
    public function returnsEmptyWhenDatabaseDsnHasWrongScheme(): void
    {
        if (\defined('DATABASE_DSN') && \constant('DATABASE_DSN') !== 'mysql://root:x@127.0.0.1/test') {
            self::markTestSkipped('DATABASE_DSN already defined by bootstrap; cannot override in-process.');
        }
        if (!\defined('DATABASE_DSN')) {
            \define('DATABASE_DSN', 'mysql://root:x@127.0.0.1/test');
        }

        self::assertSame([], (new DSQLDiscovery())->getProviders());
    }

    #[Test]
    public function returnsEmptyWhenDatabaseDsnIsMalformed(): void
    {
        if (\defined('DATABASE_DSN') && \constant('DATABASE_DSN') !== '!!!invalid%%%') {
            self::markTestSkipped('DATABASE_DSN already defined by bootstrap; cannot override in-process.');
        }
        if (!\defined('DATABASE_DSN')) {
            // An unparseable DSN triggers the InvalidDsnException branch
            \define('DATABASE_DSN', '!!!invalid%%%');
        }

        self::assertSame([], (new DSQLDiscovery())->getProviders());
    }

    #[Test]
    public function fallsBackToDbHostWhenNoDatabaseDsn(): void
    {
        // DB_HOST is pre-defined by the WordPress test bootstrap; override
        // via runkit isn't available, so we reuse whichever hostname the
        // bootstrap set. The branch under test is the "DATABASE_DSN undef
        // → consult DB_HOST" path; we just assert it produces the expected
        // empty-or-one-provider shape depending on whether DB_HOST
        // happens to match the DSQL endpoint pattern.
        if (!\defined('DB_HOST')) {
            self::markTestSkipped('DB_HOST not defined in this runtime.');
        }

        $providers = (new DSQLDiscovery())->getProviders();

        $host = (string) \constant('DB_HOST');
        if (preg_match('/^[a-z0-9]+\.dsql\.[a-z0-9-]+\.on\.aws/', $host) === 1) {
            self::assertCount(1, $providers);
        } else {
            self::assertSame([], $providers);
        }
    }
}
