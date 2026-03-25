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

namespace WpPack\Component\Mailer\Bridge\Azure\Transport;

use WpPack\Component\Mailer\Exception\InvalidArgumentException;
use WpPack\Component\Mailer\Exception\UnsupportedSchemeException;
use WpPack\Component\Mailer\Transport\Dsn;
use WpPack\Component\Mailer\Transport\TransportFactoryInterface;
use WpPack\Component\Mailer\Transport\TransportInterface;

final class AzureTransportFactory implements TransportFactoryInterface
{
    public function create(Dsn $dsn): TransportInterface
    {
        if (!$this->supports($dsn)) {
            throw new UnsupportedSchemeException($dsn);
        }

        $resourceName = $dsn->getUser() ?? '';
        $accessKey = $dsn->getPassword() ?? '';

        if ($resourceName === '' || $accessKey === '') {
            throw new InvalidArgumentException(sprintf('Azure "%s" DSN must contain a resource name (user) and access key (password).', $dsn->getScheme()));
        }

        $apiVersion = $dsn->getOption('api_version', '2024-07-01-preview');

        return new AzureApiTransport(
            resourceName: $resourceName,
            accessKey: $accessKey,
            apiVersion: $apiVersion,
        );
    }

    public function supports(Dsn $dsn): bool
    {
        return in_array($dsn->getScheme(), ['azure', 'azure+api'], true);
    }
}
