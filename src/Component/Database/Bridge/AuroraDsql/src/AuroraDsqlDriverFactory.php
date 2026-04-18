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

namespace WpPack\Component\Database\Bridge\AuroraDsql;

use WpPack\Component\Database\Driver\DriverDefinition;
use WpPack\Component\Database\Driver\DriverFactoryInterface;
use WpPack\Component\Database\Driver\DriverField;
use WpPack\Component\Database\Driver\DriverInterface;
use WpPack\Component\Dsn\Dsn;

/**
 * Factory for Aurora DSQL connections.
 *
 * DSN format: dsql://admin:token@<cluster-id>.dsql.<region>.on.aws/dbname
 * Region is extracted from the endpoint hostname.
 */
final class AuroraDsqlDriverFactory implements DriverFactoryInterface
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
        $endpoint = $dsn->getHost() ?? '';
        $region = $options['region'] ?? $dsn->getOption('region') ?? $this->extractRegionFromEndpoint($endpoint) ?? 'us-east-1';

        $occMaxRetries = $dsn->getOption('occMaxRetries');
        $tokenDurationSecs = $dsn->getOption('tokenDurationSecs');

        $searchPathRaw = $dsn->getOption('search_path') ?? $dsn->getOption('schema');
        $searchPath = null;
        if ($searchPathRaw !== null && $searchPathRaw !== '') {
            $parts = array_values(array_filter(
                array_map('trim', explode(',', $searchPathRaw)),
                static fn(string $s): bool => $s !== '',
            ));
            $searchPath = $parts === [] ? null : $parts;
        }

        return new AuroraDsqlDriver(
            endpoint: $endpoint,
            region: $region,
            database: ltrim($dsn->getPath() ?? '', '/'),
            username: $dsn->getUser() ?? 'admin',
            token: $dsn->getPassword(),
            tokenDurationSecs: $tokenDurationSecs !== null ? (int) $tokenDurationSecs : 900,
            occMaxRetries: $occMaxRetries !== null ? (int) $occMaxRetries : 3,
            credentialProvider: $options['credentialProvider'] ?? null,
            logger: $options['logger'] ?? null,
            searchPath: $searchPath,
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
