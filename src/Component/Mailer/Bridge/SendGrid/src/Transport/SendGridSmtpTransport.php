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

namespace WPPack\Component\Mailer\Bridge\SendGrid\Transport;

use WPPack\Component\Mailer\Transport\SmtpTransport;

final class SendGridSmtpTransport extends SmtpTransport
{
    public function __construct(
        #[\SensitiveParameter]
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
