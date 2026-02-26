<?php

declare(strict_types=1);

namespace WpPack\Component\Mailer\Bridge\Amazon\Transport;

use WpPack\Component\Mailer\Transport\SmtpTransport;

final class SesSmtpTransport extends SmtpTransport
{
    public function __construct(
        string $username,
        string $password,
        string $region = 'us-east-1',
        string $encryption = 'tls',
        int $port = 587,
    ) {
        parent::__construct(
            host: sprintf('email-smtp.%s.amazonaws.com', $region),
            port: $port,
            username: $username,
            password: $password,
            encryption: $encryption,
        );
    }

    public function __toString(): string
    {
        return 'ses+smtp://default';
    }
}
