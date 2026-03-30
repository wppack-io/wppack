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
use WpPack\Component\Mailer\Transport\TransportDefinition;
use WpPack\Component\Mailer\Transport\TransportFactoryInterface;
use WpPack\Component\Mailer\Transport\TransportField;
use WpPack\Component\Mailer\Transport\TransportInterface;

final class SesTransportFactory implements TransportFactoryInterface
{
    /** @var list<array{label: string, value: string}> */
    private const REGION_OPTIONS = [
        ['label' => 'us-east-1', 'value' => 'us-east-1'],
        ['label' => 'us-east-2', 'value' => 'us-east-2'],
        ['label' => 'us-west-2', 'value' => 'us-west-2'],
        ['label' => 'ap-south-1', 'value' => 'ap-south-1'],
        ['label' => 'ap-northeast-1', 'value' => 'ap-northeast-1'],
        ['label' => 'ap-northeast-2', 'value' => 'ap-northeast-2'],
        ['label' => 'ap-southeast-1', 'value' => 'ap-southeast-1'],
        ['label' => 'ap-southeast-2', 'value' => 'ap-southeast-2'],
        ['label' => 'ca-central-1', 'value' => 'ca-central-1'],
        ['label' => 'eu-central-1', 'value' => 'eu-central-1'],
        ['label' => 'eu-west-1', 'value' => 'eu-west-1'],
        ['label' => 'eu-west-2', 'value' => 'eu-west-2'],
        ['label' => 'eu-north-1', 'value' => 'eu-north-1'],
        ['label' => 'me-south-1', 'value' => 'me-south-1'],
        ['label' => 'sa-east-1', 'value' => 'sa-east-1'],
        ['label' => 'af-south-1', 'value' => 'af-south-1'],
        ['label' => 'il-central-1', 'value' => 'il-central-1'],
    ];

    public static function definitions(): array
    {
        $regionField = new TransportField('region', 'Region', required: true, default: 'us-east-1', dsnPart: 'option:region', options: self::REGION_OPTIONS, maxWidth: '200px');

        return [
            new TransportDefinition(
                scheme: 'ses+api',
                label: 'Amazon SES (API)',
                fields: [
                    new TransportField('accessKey', 'Access Key ID', dsnPart: 'user', help: 'Leave empty to use IAM role'),
                    new TransportField('secretKey', 'Secret Access Key', type: 'password', dsnPart: 'password'),
                    $regionField,
                    new TransportField('configurationSet', 'Configuration Set', dsnPart: 'option:configuration_set'),
                ],
            ),
            new TransportDefinition(
                scheme: 'ses+https',
                label: 'Amazon SES (HTTP)',
                fields: [
                    new TransportField('accessKey', 'Access Key ID', dsnPart: 'user', help: 'Leave empty to use IAM role'),
                    new TransportField('secretKey', 'Secret Access Key', type: 'password', dsnPart: 'password'),
                    $regionField,
                    new TransportField('configurationSet', 'Configuration Set', dsnPart: 'option:configuration_set'),
                ],
            ),
            new TransportDefinition(
                scheme: 'ses+smtp',
                label: 'Amazon SES (SMTP)',
                fields: [
                    new TransportField('username', 'SMTP Username', required: true, dsnPart: 'user'),
                    new TransportField('password', 'SMTP Password', type: 'password', required: true, dsnPart: 'password'),
                    $regionField,
                ],
            ),
        ];
    }

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
