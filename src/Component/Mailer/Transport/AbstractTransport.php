<?php

declare(strict_types=1);

namespace WpPack\Component\Mailer\Transport;

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use WpPack\Component\Mailer\PhpMailer;

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
    abstract protected function doSend(PhpMailer $phpMailer): void;

    public function configure(PhpMailer $phpMailer): void
    {
        $phpMailer->registerCustomMailer(
            $this->getMailerName(),
            function (PhpMailer $mailer): bool {
                try {
                    $this->doSend($mailer);
                } catch (PHPMailerException $e) {
                    throw $e;
                } catch (\Throwable $e) {
                    throw new PHPMailerException($e->getMessage(), 0, $e);
                }

                return true;
            },
        );
        $phpMailer->Mailer = $this->getMailerName();
    }

    public function __toString(): string
    {
        return $this->getMailerName() . '://default';
    }
}
