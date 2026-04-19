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

namespace WPPack\Component\Database\Bridge\Sqlite;

use WPPack\Component\Database\Driver\DriverDefinition;
use WPPack\Component\Database\Driver\DriverFactoryInterface;
use WPPack\Component\Database\Driver\DriverField;
use WPPack\Component\Database\Driver\DriverInterface;
use WPPack\Component\Dsn\Dsn;

final class SqliteDriverFactory implements DriverFactoryInterface
{
    private const SUPPORTED_SCHEMES = ['sqlite', 'sqlite3'];

    public static function definitions(): array
    {
        return [
            new DriverDefinition(
                scheme: 'sqlite',
                label: 'SQLite',
                fields: [
                    new DriverField('path', 'Database File Path', required: true, dsnPart: 'path'),
                ],
            ),
        ];
    }

    public function create(Dsn $dsn, array $options = []): DriverInterface
    {
        $path = $dsn->getPath() ?? ':memory:';

        // Strip leading slash for :memory: special path
        if ($path === '/:memory:') {
            $path = ':memory:';
        }

        return new SqliteDriver($path);
    }

    public function supports(Dsn $dsn): bool
    {
        return \in_array($dsn->getScheme(), self::SUPPORTED_SCHEMES, true)
            && \extension_loaded('pdo_sqlite');
    }
}
