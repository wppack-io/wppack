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

namespace WpPack\Component\Mailer\Bridge\Amazon\Transport;

use AsyncAws\Ses\SesClient;
use WpPack\Component\Mailer\Exception\UnsupportedSchemeException;
use WpPack\Component\Mailer\Transport\Dsn;
use WpPack\Component\Mailer\Transport\TransportFactoryInterface;
use WpPack\Component\Mailer\Transport\TransportInterface;

final class SesTransportFactory implements TransportFactoryInterface
{
    public function create(Dsn $dsn): TransportInterface
    {
        return match ($dsn->getScheme()) {
            'ses', 'ses+api' => new SesApiTransport(
                sesClient: $this->createSesClient($dsn),
                configurationSet: $dsn->getOption('configuration_set'),
            ),
            'ses+https' => new SesHttpTransport(
                sesClient: $this->createSesClient($dsn),
                configurationSet: $dsn->getOption('configuration_set'),
            ),
            'ses+smtp', 'ses+smtps' => new SesSmtpTransport(
                username: $dsn->getUser() ?? '',
                password: $dsn->getPassword() ?? '',
                region: $dsn->getOption('region', 'us-east-1'),
                encryption: $dsn->getScheme() === 'ses+smtps' ? 'ssl' : 'tls',
                port: $dsn->getPort() ?? ($dsn->getScheme() === 'ses+smtps' ? 465 : 587),
            ),
            default => throw new UnsupportedSchemeException($dsn),
        };
    }

    public function supports(Dsn $dsn): bool
    {
        return in_array($dsn->getScheme(), ['ses', 'ses+api', 'ses+https', 'ses+smtp', 'ses+smtps'], true);
    }

    private function createSesClient(Dsn $dsn): SesClient
    {
        $options = ['region' => $dsn->getOption('region', 'us-east-1')];

        $user = $dsn->getUser();
        if ($user !== null && $user !== '') {
            $options['accessKeyId'] = $user;
            $options['accessKeySecret'] = $dsn->getPassword() ?? '';

            $sessionToken = $dsn->getOption('session_token');
            if ($sessionToken !== null) {
                $options['sessionToken'] = $sessionToken;
            }
        }

        $host = $dsn->getHost();
        if ($host !== 'default') {
            $port = $dsn->getPort();
            $options['endpoint'] = 'https://' . $host . ($port !== null ? ':' . $port : '');
        }

        return new SesClient($options);
    }
}
