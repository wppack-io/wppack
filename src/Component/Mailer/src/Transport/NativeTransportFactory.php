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

use WPPack\Component\Dsn\Dsn;
use WPPack\Component\Mailer\Exception\UnsupportedSchemeException;

final class NativeTransportFactory implements TransportFactoryInterface
{
    public static function definitions(): array
    {
        return [
            new TransportDefinition(
                scheme: 'smtp',
                label: 'SMTP',
                fields: [
                    new TransportField('host', 'Host', required: true, default: 'localhost', dsnPart: 'host'),
                    new TransportField('port', 'Port', type: 'number', default: '587', dsnPart: 'port', maxWidth: '120px'),
                    new TransportField('username', 'Username', dsnPart: 'user'),
                    new TransportField('password', 'Password', type: 'password', dsnPart: 'password'),
                ],
            ),
            new TransportDefinition(
                scheme: 'native',
                label: 'PHP mail()',
            ),
        ];
    }

    public function create(Dsn $dsn): TransportInterface
    {
        return match ($dsn->getScheme()) {
            'native' => new NativeTransport(),
            'smtp', 'smtps' => new SmtpTransport(
                host: $dsn->getHost() ?? 'localhost',
                port: $dsn->getPort() ?? ($dsn->getScheme() === 'smtps' ? 465 : 587),
                username: $dsn->getUser(),
                password: $dsn->getPassword(),
                encryption: $dsn->getScheme() === 'smtps' ? 'ssl' : $dsn->getOption('encryption', 'tls'),
            ),
            'null' => new NullTransport(),
            default => throw new UnsupportedSchemeException($dsn),
        };
    }

    public function supports(Dsn $dsn): bool
    {
        return in_array($dsn->getScheme(), ['native', 'smtp', 'smtps', 'null'], true);
    }
}
