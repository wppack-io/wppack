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

namespace WPPack\Component\Mailer\Bridge\Amazon\Transport;

use AsyncAws\Ses\Input\SendEmailRequest;
use AsyncAws\Ses\SesClient;
use WPPack\Component\Mailer\Exception\TransportException;
use WPPack\Component\Mailer\Transport\AbstractTransport;
use WPPack\Component\Mailer\PhpMailer;

final class SesHttpTransport extends AbstractTransport
{
    public function __construct(
        private readonly SesClient $sesClient,
        private readonly ?string $configurationSet = null,
    ) {}

    public function getName(): string
    {
        return 'ses+https';
    }

    protected function doSend(PhpMailer $phpMailer): void
    {
        $mime = $phpMailer->getSentMIMEMessage();

        $request = [
            'Content' => ['Raw' => ['Data' => $mime]],
        ];

        if ($this->configurationSet !== null) {
            $request['ConfigurationSetName'] = $this->configurationSet;
        }

        $messageId = $this->sesClient->sendEmail(new SendEmailRequest($request))->getMessageId();

        if ($messageId === '' || $messageId === null) {
            throw new TransportException('SES email send succeeded but no message ID was returned.');
        }

        if (!str_starts_with($messageId, '<')) {
            $messageId = '<' . $messageId . '>';
        }

        $phpMailer->setLastMessageId($messageId);
    }

}
