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

namespace WPPack\Component\Cache\Adapter;

use WPPack\Component\Cache\Bridge\Apcu\ApcuAdapterFactory;
use WPPack\Component\Cache\Bridge\DynamoDb\DynamoDbAdapterFactory;
use WPPack\Component\Cache\Bridge\Memcached\MemcachedAdapterFactory;
use WPPack\Component\Cache\Bridge\Redis\Adapter\RedisAdapterFactory;
use WPPack\Component\Cache\Exception\UnsupportedSchemeException;
use WPPack\Component\Dsn\Dsn;

final class Adapter
{
    /** @var array<class-string<AdapterFactoryInterface>> */
    private const FACTORY_CLASSES = [
        RedisAdapterFactory::class,
        DynamoDbAdapterFactory::class,
        MemcachedAdapterFactory::class,
        ApcuAdapterFactory::class,
    ];

    /** @param iterable<AdapterFactoryInterface> $factories */
    public function __construct(
        private readonly iterable $factories,
    ) {}

    /**
     * @param array<string, mixed> $options
     */
    public static function fromDsn(string $dsn, array $options = []): AdapterInterface
    {
        return (new self(self::getDefaultFactories()))->fromString($dsn, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function fromString(string $dsn, array $options = []): AdapterInterface
    {
        return $this->create(Dsn::fromString($dsn), $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function create(Dsn $dsn, array $options = []): AdapterInterface
    {
        foreach ($this->factories as $factory) {
            if ($factory->supports($dsn)) {
                return $factory->create($dsn, $options);
            }
        }

        // The actual scheme set is the union of supported() across all
        // factories; hard-code the well-known names for the error
        // message so the caller doesn't need to plumb a factory-level
        // API just to render guidance.
        throw new UnsupportedSchemeException($dsn, 'Cache', [
            'redis', 'rediss', 'valkey', 'valkeys',
            'dynamodb',
            'memcached',
            'apcu',
        ]);
    }

    /** @return \Generator<int, AdapterFactoryInterface> */
    private static function getDefaultFactories(): \Generator
    {
        foreach (self::FACTORY_CLASSES as $factoryClass) {
            if (class_exists($factoryClass)) {
                yield new $factoryClass();
            }
        }
    }
}
