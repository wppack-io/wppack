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

namespace WpPack\Component\Database\Bridge\Pgsql;

use WpPack\Component\Database\Driver\DriverDefinition;
use WpPack\Component\Database\Driver\DriverFactoryInterface;
use WpPack\Component\Database\Driver\DriverField;
use WpPack\Component\Database\Driver\DriverInterface;
use WpPack\Component\Dsn\Dsn;

final class PgsqlDriverFactory implements DriverFactoryInterface
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
        return new PgsqlDriver(
            host: $dsn->getHost() ?? '127.0.0.1',
            username: $dsn->getUser() ?? '',
            password: $dsn->getPassword() ?? '',
            database: ltrim($dsn->getPath() ?? '', '/'),
            port: $dsn->getPort() ?? 5432,
        );
    }

    public function supports(Dsn $dsn): bool
    {
        return \in_array($dsn->getScheme(), self::SUPPORTED_SCHEMES, true)
            && \function_exists('pg_connect');
    }
}
