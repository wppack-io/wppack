<?php

declare(strict_types=1);

namespace WpPack\Component\Mailer\Bridge\Azure\Transport;

use WpPack\Component\Mailer\Exception\UnsupportedSchemeException;
use WpPack\Component\Mailer\Transport\Dsn;
use WpPack\Component\Mailer\Transport\TransportFactoryInterface;
use WpPack\Component\Mailer\Transport\TransportInterface;

final class AzureTransportFactory implements TransportFactoryInterface
{
    public function create(Dsn $dsn): TransportInterface
    {
        $endpoint = $dsn->getUser() ?? '';
        $accessKey = $dsn->getPassword() ?? '';
        $apiVersion = $dsn->getOption('api_version', '2024-07-01-preview');

        return match ($dsn->getScheme()) {
            'azure+api' => new AzureApiTransport(
                endpoint: $endpoint,
                accessKey: $accessKey,
                apiVersion: $apiVersion,
            ),
            'azure', 'azure+https' => new AzureTransport(
                endpoint: $endpoint,
                accessKey: $accessKey,
                apiVersion: $apiVersion,
            ),
            default => throw new UnsupportedSchemeException($dsn),
        };
    }

    public function supports(Dsn $dsn): bool
    {
        return in_array($dsn->getScheme(), ['azure', 'azure+api', 'azure+https'], true);
    }
}
