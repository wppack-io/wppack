<?php

declare(strict_types=1);

namespace WpPack\Component\Mailer\Transport;

use WpPack\Component\Mailer\WpPackPhpMailer;

class SmtpTransport implements TransportInterface
{
    public function __construct(
        private readonly string $host,
        private readonly int $port = 587,
        private readonly ?string $username = null,
        private readonly ?string $password = null,
        private readonly string $encryption = 'tls',
    ) {}

    public function configure(WpPackPhpMailer $phpMailer): void
    {
        $phpMailer->isSMTP();
        $phpMailer->Host = $this->host;
        $phpMailer->Port = $this->port;
        $phpMailer->SMTPSecure = $this->encryption;

        if ($this->username !== null) {
            $phpMailer->SMTPAuth = true;
            $phpMailer->Username = $this->username;
            $phpMailer->Password = $this->password ?? '';
        }
    }

    public function __toString(): string
    {
        return sprintf('smtp://%s:%d', $this->host, $this->port);
    }
}
