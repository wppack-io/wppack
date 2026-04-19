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

namespace WPPack\Component\Storage\Adapter;

use WPPack\Component\Storage\Bridge\Azure\AzureStorageAdapterFactory;
use WPPack\Component\Storage\Bridge\Gcs\GcsStorageAdapterFactory;
use WPPack\Component\Storage\Bridge\S3\S3StorageAdapterFactory;
use WPPack\Component\Storage\Exception\UnsupportedSchemeException;

final class Storage
{
    /** @var array<class-string<StorageAdapterFactoryInterface>> */
    private const FACTORY_CLASSES = [
        LocalStorageAdapterFactory::class,
        S3StorageAdapterFactory::class,
        AzureStorageAdapterFactory::class,
        GcsStorageAdapterFactory::class,
    ];

    /** @param iterable<StorageAdapterFactoryInterface> $factories */
    public function __construct(
        private readonly iterable $factories,
    ) {}

    /** @param array<string, mixed> $options */
    public static function fromDsn(string $dsn, array $options = []): StorageAdapterInterface
    {
        return (new self(self::getDefaultFactories()))->fromString($dsn, $options);
    }

    /** @param array<string, mixed> $options */
    public function fromString(string $dsn, array $options = []): StorageAdapterInterface
    {
        return $this->create(Dsn::fromString($dsn), $options);
    }

    /** @param array<string, mixed> $options */
    public function create(Dsn $dsn, array $options = []): StorageAdapterInterface
    {
        foreach ($this->factories as $factory) {
            if ($factory->supports($dsn)) {
                return $factory->create($dsn, $options);
            }
        }

        throw new UnsupportedSchemeException($dsn);
    }

    /** @return \Generator<int, StorageAdapterFactoryInterface> */
    private static function getDefaultFactories(): \Generator
    {
        foreach (self::FACTORY_CLASSES as $factoryClass) {
            if (class_exists($factoryClass)) {
                yield new $factoryClass();
            }
        }
    }
}
