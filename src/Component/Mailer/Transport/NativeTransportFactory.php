<?php

declare(strict_types=1);

namespace WpPack\Component\Mailer\Transport;

use WpPack\Component\Mailer\Exception\UnsupportedSchemeException;

final class NativeTransportFactory implements TransportFactoryInterface
{
    public function create(Dsn $dsn): TransportInterface
    {
        return match ($dsn->getScheme()) {
            'native' => new NativeTransport(),
            'smtp' => new SmtpTransport(
                host: $dsn->getHost(),
                port: $dsn->getPort() ?? 587,
                username: $dsn->getUser(),
                password: $dsn->getPassword(),
                encryption: $dsn->getOption('encryption', 'tls'),
            ),
            'null' => new NullTransport(),
            default => throw new UnsupportedSchemeException($dsn),
        };
    }

    public function supports(Dsn $dsn): bool
    {
        return in_array($dsn->getScheme(), ['native', 'smtp', 'null'], true);
    }
}
