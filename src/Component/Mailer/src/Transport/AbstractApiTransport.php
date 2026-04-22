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

use WPPack\Component\Mailer\PhpMailer;

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

    /**
     * Narrow a PHPMailer address array to the expected (email, name) shape.
     *
     * PHPMailer stubs type recipient entries as `array<int, array>` (loose);
     * runtime always provides `[email, name]` as two strings. Narrow at the
     * boundary so tuple-typed helpers (formatAddress, formatRecipient) don't
     * need to re-validate.
     *
     * @param array<int, mixed> $addr
     * @return array{0: string, 1: string}
     */
    protected static function narrowAddressTuple(array $addr): array
    {
        $email = $addr[0] ?? null;
        $name = $addr[1] ?? null;

        return [
            \is_string($email) ? $email : '',
            \is_string($name) ? $name : '',
        ];
    }
}
