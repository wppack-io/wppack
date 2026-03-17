<?php

declare(strict_types=1);

namespace WpPack\Component\Storage\Adapter;

use WpPack\Component\Storage\Bridge\S3\S3StorageAdapterFactory;
use WpPack\Component\Storage\Exception\UnsupportedSchemeException;

final class Storage
{
    /** @var array<class-string<StorageAdapterFactoryInterface>> */
    private const FACTORY_CLASSES = [
        S3StorageAdapterFactory::class,
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
