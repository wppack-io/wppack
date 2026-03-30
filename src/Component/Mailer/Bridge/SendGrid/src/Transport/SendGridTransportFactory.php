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

namespace WpPack\Component\Mailer\Bridge\SendGrid\Transport;

use WpPack\Component\Mailer\Exception\InvalidArgumentException;
use WpPack\Component\Mailer\Exception\UnsupportedSchemeException;
use WpPack\Component\Mailer\Transport\Dsn;
use WpPack\Component\Mailer\Transport\TransportDefinition;
use WpPack\Component\Mailer\Transport\TransportFactoryInterface;
use WpPack\Component\Mailer\Transport\TransportField;
use WpPack\Component\Mailer\Transport\TransportInterface;

final class SendGridTransportFactory implements TransportFactoryInterface
{
    public static function definitions(): array
    {
        return [
            new TransportDefinition(
                scheme: 'sendgrid+api',
                label: 'SendGrid (API)',
                fields: [
                    new TransportField('apiKey', 'API Key', type: 'password', required: true, dsnPart: 'user'),
                ],
            ),
            new TransportDefinition(
                scheme: 'sendgrid+smtp',
                label: 'SendGrid (SMTP)',
                fields: [
                    new TransportField('apiKey', 'API Key', type: 'password', required: true, dsnPart: 'password'),
                ],
            ),
        ];
    }

    public function create(Dsn $dsn): TransportInterface
    {
        if (!$this->supports($dsn)) {
            throw new UnsupportedSchemeException($dsn);
        }

        return match ($dsn->getScheme()) {
            'sendgrid+smtp', 'sendgrid+smtps' => new SendGridSmtpTransport(
                apiKey: $dsn->getPassword() ?? throw new InvalidArgumentException(sprintf('SendGrid "%s" DSN must contain an API key (password).', $dsn->getScheme())),
                encryption: $dsn->getScheme() === 'sendgrid+smtps' ? 'ssl' : 'tls',
                port: $dsn->getPort() ?? ($dsn->getScheme() === 'sendgrid+smtps' ? 465 : 587),
            ),
            default => new SendGridApiTransport(
                apiKey: $dsn->getUser() ?? throw new InvalidArgumentException(sprintf('SendGrid "%s" DSN must contain an API key (user).', $dsn->getScheme())),
            ),
        };
    }

    public function supports(Dsn $dsn): bool
    {
        return in_array($dsn->getScheme(), [
            'sendgrid',
            'sendgrid+api',
            'sendgrid+smtp',
            'sendgrid+smtps',
        ], true);
    }
}
