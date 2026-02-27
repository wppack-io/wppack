<?php

declare(strict_types=1);

namespace WpPack\Component\Mailer\Bridge\SendGrid\Transport;

use WpPack\Component\Mailer\Transport\SmtpTransport;

final class SendGridSmtpTransport extends SmtpTransport
{
    public function __construct(
        string $apiKey,
        string $encryption = 'tls',
        int $port = 587,
    ) {
        parent::__construct(
            host: 'smtp.sendgrid.net',
            port: $port,
            username: 'apikey',
            password: $apiKey,
            encryption: $encryption,
        );
    }

    public function getName(): string
    {
        return 'sendgrid+smtp';
    }

}
