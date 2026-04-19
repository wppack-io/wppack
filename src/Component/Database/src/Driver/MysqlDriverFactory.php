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

namespace WPPack\Component\Database\Driver;

use WPPack\Component\Dsn\Dsn;

final class MysqlDriverFactory implements DriverFactoryInterface
{
    private const SUPPORTED_SCHEMES = ['mysql', 'mariadb', 'mysqli'];

    public static function definitions(): array
    {
        return [
            new DriverDefinition(
                scheme: 'mysql',
                label: 'MySQL / MariaDB',
                fields: [
                    new DriverField('host', 'Host', default: '127.0.0.1', dsnPart: 'host'),
                    new DriverField('port', 'Port', type: 'number', default: '3306', dsnPart: 'port'),
                    new DriverField('username', 'Username', dsnPart: 'user'),
                    new DriverField('password', 'Password', type: 'password', dsnPart: 'password'),
                    new DriverField('database', 'Database', required: true, dsnPart: 'path'),
                ],
            ),
        ];
    }

    public function create(Dsn $dsn, array $options = []): DriverInterface
    {
        return new MysqlDriver(
            host: $dsn->getHost() ?? '127.0.0.1',
            username: $dsn->getUser() ?? '',
            password: $dsn->getPassword() ?? '',
            database: ltrim($dsn->getPath() ?? '', '/'),
            port: $dsn->getPort() ?? 3306,
            charset: $dsn->getOption('charset', 'utf8mb4') ?? 'utf8mb4',
        );
    }

    public function supports(Dsn $dsn): bool
    {
        return \in_array($dsn->getScheme(), self::SUPPORTED_SCHEMES, true);
    }
}
