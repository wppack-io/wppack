<?php

declare(strict_types=1);

namespace WpPack\Component\Mailer\Transport;

use WpPack\Component\Mailer\WpPackPhpMailer;

abstract class AbstractApiTransport extends AbstractTransport
{
    /**
     * Build and send a structured API request from PHPMailer properties.
     *
     * @return string Message ID
     */
    abstract protected function doSendApi(WpPackPhpMailer $phpMailer): string;

    protected function doSend(WpPackPhpMailer $phpMailer): void
    {
        $messageId = $this->doSendApi($phpMailer);
        $phpMailer->setLastMessageId('<' . $messageId . '>');
    }

    /**
     * Format a PHPMailer address array to "Name <email>" string.
     *
     * @param array{0: string, 1: string} $addr
     */
    protected function formatAddress(array $addr): string
    {
        if (empty($addr[1])) {
            return $addr[0];
        }

        return sprintf('"%s" <%s>', str_replace(['\\', '"'], ['\\\\', '\\"'], $addr[1]), $addr[0]);
    }
}
