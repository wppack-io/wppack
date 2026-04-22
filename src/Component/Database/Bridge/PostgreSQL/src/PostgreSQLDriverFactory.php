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

namespace WPPack\Component\Database\Bridge\PostgreSQL;

use WPPack\Component\Database\Driver\DriverDefinition;
use WPPack\Component\Database\Driver\DriverFactoryInterface;
use WPPack\Component\Database\Driver\DriverField;
use WPPack\Component\Database\Driver\DriverInterface;
use WPPack\Component\Database\Exception\ConnectionException;
use WPPack\Component\Dsn\Dsn;

final class PostgreSQLDriverFactory implements DriverFactoryInterface
{
    private const SUPPORTED_SCHEMES = ['pgsql', 'postgresql', 'postgres'];

    public static function definitions(): array
    {
        return [
            new DriverDefinition(
                scheme: 'pgsql',
                label: 'PostgreSQL',
                fields: [
                    new DriverField('host', 'Host', default: '127.0.0.1', dsnPart: 'host'),
                    new DriverField('port', 'Port', type: 'number', default: '5432', dsnPart: 'port'),
                    new DriverField('username', 'Username', dsnPart: 'user'),
                    new DriverField('password', 'Password', type: 'password', dsnPart: 'password'),
                    new DriverField('database', 'Database', required: true, dsnPart: 'path'),
                ],
            ),
        ];
    }

    public function create(Dsn $dsn, array $options = []): DriverInterface
    {
        $host = $dsn->getHost();
        if ($host === null || $host === '') {
            throw new ConnectionException(sprintf(
                'PostgreSQL DSN is missing the host component: %s',
                $dsn->getScheme() . '://…',
            ));
        }

        return new PostgreSQLDriver(
            host: $host,
            username: $dsn->getUser(),
            password: $dsn->getPassword(),
            database: ltrim($dsn->getPath() ?? '', '/'),
            port: $dsn->getPort() ?? 5432,
            searchPath: self::parseSearchPath($dsn),
        );
    }

    /**
     * Read `?search_path=a,b,c` (preferred) or `?schema=foo` (ergonomic
     * single-schema alias) from the DSN query string. Returns null when
     * neither is set, so the server-side default applies.
     *
     * Shared with AuroraDSQLDriverFactory — any DSN extension that
     * targets a pg-compatible engine should parse the same options so
     * multi-tenant / blog-scoped search_path works uniformly.
     *
     * @return list<string>|null
     */
    public static function parseSearchPath(Dsn $dsn): ?array
    {
        $raw = $dsn->getOption('search_path') ?? $dsn->getOption('schema');
        if ($raw === null || $raw === '') {
            return null;
        }

        $parts = array_values(array_filter(
            array_map('trim', explode(',', $raw)),
            static fn(string $s): bool => $s !== '',
        ));

        return $parts === [] ? null : $parts;
    }

    public function supports(Dsn $dsn): bool
    {
        return \in_array($dsn->getScheme(), self::SUPPORTED_SCHEMES, true)
            && \function_exists('pg_connect');
    }
}
