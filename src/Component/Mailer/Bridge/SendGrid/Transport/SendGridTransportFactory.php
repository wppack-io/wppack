<?php

declare(strict_types=1);

namespace WpPack\Component\Mailer\Bridge\SendGrid\Transport;

use WpPack\Component\Mailer\Exception\InvalidArgumentException;
use WpPack\Component\Mailer\Exception\UnsupportedSchemeException;
use WpPack\Component\Mailer\Transport\Dsn;
use WpPack\Component\Mailer\Transport\TransportFactoryInterface;
use WpPack\Component\Mailer\Transport\TransportInterface;

final class SendGridTransportFactory implements TransportFactoryInterface
{
    public function create(Dsn $dsn): TransportInterface
    {
        if (!$this->supports($dsn)) {
            throw new UnsupportedSchemeException($dsn);
        }

        return match ($dsn->getScheme()) {
            'sendgrid+smtp', 'sendgrid+smtps' => new SendGridSmtpTransport(
                apiKey: $dsn->getPassword() ?? throw new InvalidArgumentException(sprintf('SendGrid SMTP DSN "%s" must contain an API key (password).', $dsn)),
                encryption: $dsn->getScheme() === 'sendgrid+smtps' ? 'ssl' : 'tls',
                port: $dsn->getPort() ?? ($dsn->getScheme() === 'sendgrid+smtps' ? 465 : 587),
            ),
            default => new SendGridApiTransport(
                apiKey: $dsn->getUser() ?? throw new InvalidArgumentException(sprintf('SendGrid API DSN "%s" must contain an API key (user).', $dsn)),
            ),
        };
    }

    public function supports(Dsn $dsn): bool
    {
        return in_array($dsn->getScheme(), [
            'sendgrid',
            'sendgrid+https',
            'sendgrid+api',
            'sendgrid+smtp',
            'sendgrid+smtps',
        ], true);
    }
}
