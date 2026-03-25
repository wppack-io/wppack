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

    public function getName(): string
    {
        return 'ses+smtp';
    }

}
