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

namespace WpPack\Component\Database\Driver;

use WpPack\Component\Dsn\Dsn;

final class MysqlDriverFactory implements DriverFactoryInterface
{
    private const SUPPORTED_SCHEMES = ['mysql', 'mariadb', 'mysqli', 'wpdb'];

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
            new DriverDefinition(
                scheme: 'wpdb',
                label: 'WordPress Default (wpdb)',
            ),
        ];
    }

    public function create(Dsn $dsn, array $options = []): DriverInterface
    {
        if ($dsn->getScheme() === 'wpdb') {
            global $wpdb;

            if (!isset($wpdb->dbh) || !$wpdb->dbh instanceof \mysqli) {
                throw new \RuntimeException('wpdb:// scheme requires a valid mysqli connection in $wpdb->dbh.');
            }

            return MysqlDriver::fromMysqli($wpdb->dbh);
        }

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
