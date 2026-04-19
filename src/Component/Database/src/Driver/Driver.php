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

use WPPack\Component\Database\Exception\UnsupportedSchemeException;
use WPPack\Component\Dsn\Dsn;

final class Driver
{
    /**
     * Explicit scheme → factory-class map for the default (zero-injection)
     * path used by fromDsn(). Using an exact map instead of iterating through
     * factories guards against accidental scheme overlap (e.g. a future
     * factory claiming `mysql` would shadow `mysql+dataapi` if an iteration
     * happened to hit it first) and keeps routing deterministic regardless
     * of autoloader / file-system ordering.
     *
     * Factories whose composer package is not installed are skipped when the
     * scheme is looked up — class_exists() short-circuits to the usual
     * UnsupportedSchemeException rather than crashing on a missing class.
     *
     * @var array<string, class-string<DriverFactoryInterface>>
     */
    private const SCHEME_TO_FACTORY = [
        'mysql'         => MysqlDriverFactory::class,
        'mariadb'       => MysqlDriverFactory::class,
        'mysqli'        => MysqlDriverFactory::class,
        'sqlite'        => 'WPPack\Component\Database\Bridge\Sqlite\SqliteDriverFactory',
        'sqlite3'       => 'WPPack\Component\Database\Bridge\Sqlite\SqliteDriverFactory',
        'pgsql'         => 'WPPack\Component\Database\Bridge\Pgsql\PgsqlDriverFactory',
        'postgresql'    => 'WPPack\Component\Database\Bridge\Pgsql\PgsqlDriverFactory',
        'postgres'      => 'WPPack\Component\Database\Bridge\Pgsql\PgsqlDriverFactory',
        'mysql+dataapi' => 'WPPack\Component\Database\Bridge\MysqlDataApi\MysqlDataApiDriverFactory',
        'pgsql+dataapi' => 'WPPack\Component\Database\Bridge\PgsqlDataApi\PgsqlDataApiDriverFactory',
        'dsql'          => 'WPPack\Component\Database\Bridge\AuroraDsql\AuroraDsqlDriverFactory',
    ];

    /** @param iterable<DriverFactoryInterface> $factories */
    public function __construct(
        private readonly iterable $factories,
    ) {}

    /** @param array<string, mixed> $options */
    public static function fromDsn(string $dsn, array $options = []): DriverInterface
    {
        $parsed = Dsn::fromString($dsn);
        $scheme = $parsed->getScheme();

        $factoryClass = self::SCHEME_TO_FACTORY[$scheme] ?? null;

        if ($factoryClass === null || !class_exists($factoryClass)) {
            throw new UnsupportedSchemeException($parsed);
        }

        $factory = new $factoryClass();

        // Factories still get the final say via supports() — that's where
        // extension / class_exists() availability gates live (pdo_sqlite,
        // pg_connect, RdsDataServiceClient). If the library is installed
        // but the runtime doesn't actually support it, we surface the same
        // UnsupportedSchemeException rather than letting the factory
        // explode later with a cryptic missing-function error.
        if (!$factory->supports($parsed)) {
            throw new UnsupportedSchemeException($parsed);
        }

        return $factory->create($parsed, $options);
    }

    /** @param array<string, mixed> $options */
    public function fromString(string $dsn, array $options = []): DriverInterface
    {
        return $this->create(Dsn::fromString($dsn), $options);
    }

    /**
     * Instance-level create() retains the iterate-and-ask-supports() flow
     * for callers who inject a custom factory set (tests, DI containers
     * that register extra drivers). The scheme-map shortcut is reserved
     * for the default static entry point.
     *
     * @param array<string, mixed> $options
     */
    public function create(Dsn $dsn, array $options = []): DriverInterface
    {
        foreach ($this->factories as $factory) {
            if ($factory->supports($dsn)) {
                return $factory->create($dsn, $options);
            }
        }

        throw new UnsupportedSchemeException($dsn);
    }
}
