<?php

declare(strict_types=1);

namespace WpPack\Component\Cache\Adapter;

use WpPack\Component\Cache\Bridge\Redis\Adapter\RedisAdapterFactory;
use WpPack\Component\Cache\Exception\UnsupportedSchemeException;

final class Adapter
{
    /** @var array<class-string<AdapterFactoryInterface>> */
    private const FACTORY_CLASSES = [
        RedisAdapterFactory::class,
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

        throw new UnsupportedSchemeException($dsn);
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
