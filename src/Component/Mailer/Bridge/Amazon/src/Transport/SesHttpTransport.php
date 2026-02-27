<?php

declare(strict_types=1);

namespace WpPack\Component\Mailer\Bridge\Amazon\Transport;

use AsyncAws\Ses\Input\SendEmailRequest;
use AsyncAws\Ses\SesClient;
use WpPack\Component\Mailer\Exception\TransportException;
use WpPack\Component\Mailer\Transport\AbstractTransport;
use WpPack\Component\Mailer\PhpMailer;

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
