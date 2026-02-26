<?php

declare(strict_types=1);

namespace WpPack\Component\Mailer\Bridge\Amazon\Transport;

use AsyncAws\Ses\Input\SendEmailRequest;
use AsyncAws\Ses\SesClient;
use WpPack\Component\Mailer\Transport\AbstractTransport;
use WpPack\Component\Mailer\WpPackPhpMailer;

final class SesTransport extends AbstractTransport
{
    public function __construct(
        private readonly SesClient $sesClient,
        private readonly ?string $configurationSet = null,
    ) {}

    protected function getMailerName(): string
    {
        return 'ses';
    }

    protected function doSend(WpPackPhpMailer $phpMailer): void
    {
        $mime = $phpMailer->getSentMIMEMessage();

        $request = [
            'Content' => ['Raw' => ['Data' => $mime]],
        ];

        if ($this->configurationSet !== null) {
            $request['ConfigurationSetName'] = $this->configurationSet;
        }

        $result = $this->sesClient->sendEmail(new SendEmailRequest($request));
        $phpMailer->setLastMessageId('<' . $result->getMessageId() . '>');
    }

    public function __toString(): string
    {
        return 'ses://default';
    }
}
