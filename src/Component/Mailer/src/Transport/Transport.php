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

namespace WPPack\Component\Mailer\Transport;

use WPPack\Component\Mailer\Bridge\Amazon\Transport\SesTransportFactory;
use WPPack\Component\Mailer\Bridge\Azure\Transport\AzureTransportFactory;
use WPPack\Component\Mailer\Bridge\SendGrid\Transport\SendGridTransportFactory;
use WPPack\Component\Mailer\Exception\UnsupportedSchemeException;

final class Transport
{
    /** @var array<class-string<TransportFactoryInterface>> */
    private const FACTORY_CLASSES = [
        SesTransportFactory::class,
        AzureTransportFactory::class,
        SendGridTransportFactory::class,
    ];

    /** @param iterable<TransportFactoryInterface> $factories */
    public function __construct(
        private readonly iterable $factories,
    ) {}

    /**
     * Create a transport from a DSN string with auto-detected factories.
     *
     * Installed bridge packages (e.g. wppack/amazon-mailer) are automatically
     * detected via class_exists(). Core transports (native, smtp, null) are
     * always available.
     */
    public static function fromDsn(string $dsn): TransportInterface
    {
        return (new self(self::getDefaultFactories()))->fromString($dsn);
    }

    /**
     * Create a transport from a DSN string using injected factories.
     *
     * Use this when Transport is instantiated via DI container.
     */
    public function fromString(string $dsn): TransportInterface
    {
        return $this->create(Dsn::fromString($dsn));
    }

    public function create(Dsn $dsn): TransportInterface
    {
        foreach ($this->factories as $factory) {
            if ($factory->supports($dsn)) {
                return $factory->create($dsn);
            }
        }

        throw new UnsupportedSchemeException($dsn);
    }

    /** @return \Generator<int, TransportFactoryInterface> */
    private static function getDefaultFactories(): \Generator
    {
        foreach (self::FACTORY_CLASSES as $factoryClass) {
            if (class_exists($factoryClass)) {
                yield new $factoryClass();
            }
        }

        yield new NativeTransportFactory();
    }
}
