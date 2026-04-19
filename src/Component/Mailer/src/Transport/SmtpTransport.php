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

namespace WPPack\Component\Mailer\Transport;

use WPPack\Component\Mailer\Exception\TransportException;
use WPPack\Component\Mailer\PhpMailer;

class SmtpTransport extends AbstractTransport
{
    public function __construct(
        private readonly string $host,
        private readonly int $port = 587,
        private readonly ?string $username = null,
        #[\SensitiveParameter]
        private readonly ?string $password = null,
        private readonly string $encryption = 'tls',
    ) {}

    public function getName(): string
    {
        return 'smtp';
    }

    protected function doSend(PhpMailer $phpMailer): void
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

        try {
            if (!$phpMailer->nativePostSend()) {
                throw new TransportException('SMTP send failed: ' . $phpMailer->ErrorInfo);
            }
        } catch (TransportException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new TransportException('SMTP send failed: ' . $e->getMessage(), 0, $e);
        }
    }

}
