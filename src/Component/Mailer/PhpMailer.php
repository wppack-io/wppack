<?php

declare(strict_types=1);

namespace WpPack\Component\Mailer;

use PHPMailer\PHPMailer\PHPMailer as BasePhpMailer;
use WpPack\Component\Mailer\Transport\TransportInterface;

class PhpMailer extends BasePhpMailer
{
    private ?TransportInterface $transport = null;

    public function setTransport(TransportInterface $transport): void
    {
        $this->transport = $transport;
        $this->Mailer = $transport->getName();
    }

    public function setLastMessageId(string $messageId): void
    {
        $this->lastMessageID = $messageId;
    }

    public function postSend(): bool
    {
        if ($this->transport !== null) {
            try {
                $this->transport->send($this);
            } catch (\Throwable $e) {
                $this->setError($e->getMessage());
                $this->edebug($e->getMessage());

                throw $e;
            }

            return true;
        }

        return parent::postSend();
    }

    /**
     * Expose parent's postSend() for transports that delegate
     * to PHPMailer's built-in mailers (SMTP, mail, sendmail).
     */
    public function nativePostSend(): bool
    {
        return parent::postSend();
    }
}
