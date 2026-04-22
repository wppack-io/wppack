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

namespace WPPack\Component\Database\Bridge\AuroraDSQL;

use WPPack\Component\Database\Driver\DriverDefinition;
use WPPack\Component\Database\Driver\DriverFactoryInterface;
use WPPack\Component\Database\Driver\DriverField;
use WPPack\Component\Database\Driver\DriverInterface;
use WPPack\Component\Database\Exception\ConnectionException;
use WPPack\Component\Dsn\Dsn;

/**
 * Factory for Aurora DSQL connections.
 *
 * DSN format: dsql://admin:token@<cluster-id>.dsql.<region>.on.aws/dbname
 * Region is extracted from the endpoint hostname.
 */
final class AuroraDSQLDriverFactory implements DriverFactoryInterface
{
    public static function definitions(): array
    {
        return [
            new DriverDefinition(
                scheme: 'dsql',
                label: 'Aurora DSQL',
                fields: [
                    new DriverField('endpoint', 'Endpoint', required: true, dsnPart: 'host'),
                    new DriverField('username', 'Username', default: 'admin', dsnPart: 'user'),
                    new DriverField('database', 'Database', required: true, dsnPart: 'path'),
                ],
            ),
        ];
    }

    public function create(Dsn $dsn, array $options = []): DriverInterface
    {
        $endpoint = $dsn->getHost();
        if ($endpoint === null || $endpoint === '') {
            throw new ConnectionException('Aurora DSQL DSN is missing the endpoint (host component).');
        }

        $region = $options['region'] ?? $dsn->getOption('region') ?? $this->extractRegionFromEndpoint($endpoint) ?? 'us-east-1';

        $occMaxRetries = $dsn->getOption('occMaxRetries');
        $tokenDurationSecs = $dsn->getOption('tokenDurationSecs');

        return new AuroraDSQLDriver(
            endpoint: $endpoint,
            region: $region,
            database: ltrim($dsn->getPath() ?? '', '/'),
            username: $dsn->getUser() ?? 'admin',
            token: $dsn->getPassword(),
            tokenDurationSecs: $tokenDurationSecs !== null ? (int) $tokenDurationSecs : 900,
            occMaxRetries: $occMaxRetries !== null ? (int) $occMaxRetries : 3,
            credentialProvider: $options['credentialProvider'] ?? null,
            logger: $options['logger'] ?? null,
            searchPath: \WPPack\Component\Database\Bridge\PostgreSQL\PostgreSQLDriverFactory::parseSearchPath($dsn),
        );
    }

    public function supports(Dsn $dsn): bool
    {
        return $dsn->getScheme() === 'dsql'
            && \function_exists('pg_connect');
    }

    /**
     * Extract region from DSQL endpoint hostname.
     *
     * Example: abc123.dsql.us-east-1.on.aws → us-east-1
     */
    private function extractRegionFromEndpoint(string $endpoint): ?string
    {
        if (preg_match('/\.dsql\.(.+?)\.on\.aws$/', $endpoint, $m)) {
            return $m[1];
        }

        return null;
    }
}
