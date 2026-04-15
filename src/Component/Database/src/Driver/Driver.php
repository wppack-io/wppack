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

use WpPack\Component\Database\Exception\UnsupportedSchemeException;
use WpPack\Component\Dsn\Dsn;

final class Driver
{
    /**
     * Bridge factory classes are discovered at runtime if their packages are installed.
     *
     * @var list<string>
     */
    private const FACTORY_CLASSES = [
        MysqlDriverFactory::class,
        'WpPack\Component\Database\Bridge\Sqlite\SqliteDriverFactory',
        'WpPack\Component\Database\Bridge\Pgsql\PgsqlDriverFactory',
        'WpPack\Component\Database\Bridge\MysqlDataApi\MysqlDataApiDriverFactory',
        'WpPack\Component\Database\Bridge\PgsqlDataApi\PgsqlDataApiDriverFactory',
        'WpPack\Component\Database\Bridge\AuroraDsql\AuroraDsqlDriverFactory',
    ];

    /** @param iterable<DriverFactoryInterface> $factories */
    public function __construct(
        private readonly iterable $factories,
    ) {}

    /** @param array<string, mixed> $options */
    public static function fromDsn(string $dsn, array $options = []): DriverInterface
    {
        return (new self(self::getDefaultFactories()))->fromString($dsn, $options);
    }

    /** @param array<string, mixed> $options */
    public function fromString(string $dsn, array $options = []): DriverInterface
    {
        return $this->create(Dsn::fromString($dsn), $options);
    }

    /** @param array<string, mixed> $options */
    public function create(Dsn $dsn, array $options = []): DriverInterface
    {
        foreach ($this->factories as $factory) {
            if ($factory->supports($dsn)) {
                return $factory->create($dsn, $options);
            }
        }

        throw new UnsupportedSchemeException($dsn);
    }

    /** @return \Generator<int, DriverFactoryInterface> */
    private static function getDefaultFactories(): \Generator
    {
        foreach (self::FACTORY_CLASSES as $factoryClass) {
            if (class_exists($factoryClass)) {
                yield new $factoryClass();
            }
        }
    }
}
