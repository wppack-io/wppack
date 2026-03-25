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

namespace WpPack\Component\Mailer\Transport;

use WpPack\Component\Mailer\PhpMailer;

abstract class AbstractApiTransport extends AbstractTransport
{
    /**
     * Build and send a structured API request from PHPMailer properties.
     *
     * @return string Message ID
     */
    abstract protected function doSendApi(PhpMailer $phpMailer): string;

    protected function doSend(PhpMailer $phpMailer): void
    {
        $messageId = $this->doSendApi($phpMailer);

        if (!str_starts_with($messageId, '<')) {
            $messageId = '<' . $messageId . '>';
        }

        $phpMailer->setLastMessageId($messageId);
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
