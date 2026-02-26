<?php

declare(strict_types=1);

namespace WpPack\Component\Mailer\Transport;

use WpPack\Component\Mailer\WpPackPhpMailer;

abstract class AbstractTransport implements TransportInterface
{
    /**
     * Custom mailer name (set as PHPMailer's $Mailer property).
     */
    abstract protected function getMailerName(): string;

    /**
     * Custom send logic. Called after PHPMailer has built MIME via preSend().
     * Set $phpMailer->lastMessageID if a message ID is obtained.
     */
    abstract protected function doSend(WpPackPhpMailer $phpMailer): void;

    public function configure(WpPackPhpMailer $phpMailer): void
    {
        $phpMailer->registerCustomMailer(
            $this->getMailerName(),
            function (WpPackPhpMailer $mailer): bool {
                try {
                    $this->doSend($mailer);
                } catch (\PHPMailer\PHPMailer\Exception $e) {
                    throw $e;
                } catch (\Throwable $e) {
                    throw new \PHPMailer\PHPMailer\Exception($e->getMessage(), 0, $e);
                }

                return true;
            },
        );
        $phpMailer->Mailer = $this->getMailerName();
    }

    public function __toString(): string
    {
        return $this->getMailerName() . '://';
    }
}
