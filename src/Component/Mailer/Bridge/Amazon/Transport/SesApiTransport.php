<?php

declare(strict_types=1);

namespace WpPack\Component\Mailer\Bridge\Amazon\Transport;

use AsyncAws\Ses\Input\SendEmailRequest;
use AsyncAws\Ses\SesClient;
use WpPack\Component\Mailer\Transport\AbstractApiTransport;
use WpPack\Component\Mailer\WpPackPhpMailer;

final class SesApiTransport extends AbstractApiTransport
{
    public function __construct(
        private readonly SesClient $sesClient,
        private readonly ?string $configurationSet = null,
    ) {}

    protected function getMailerName(): string
    {
        return 'sesapi';
    }

    protected function doSendApi(WpPackPhpMailer $phpMailer): string
    {
        // Fall back to Raw for attachments (Simple doesn't support them)
        if (!empty($phpMailer->getAttachments())) {
            return $this->sendRawFallback($phpMailer);
        }

        $request = [
            'FromEmailAddress' => $this->formatAddress([$phpMailer->From, $phpMailer->FromName]),
            'Destination' => $this->buildDestination($phpMailer),
            'Content' => [
                'Simple' => [
                    'Subject' => ['Data' => $phpMailer->Subject, 'Charset' => $phpMailer->CharSet],
                    'Body' => $this->buildSimpleBody($phpMailer),
                ],
            ],
        ];

        $replyTo = $phpMailer->getReplyToAddresses();
        if (!empty($replyTo)) {
            $request['ReplyToAddresses'] = array_map(
                fn(array $addr): string => $this->formatAddress($addr),
                $replyTo,
            );
        }

        if ($this->configurationSet !== null) {
            $request['ConfigurationSetName'] = $this->configurationSet;
        }

        return $this->sesClient->sendEmail(new SendEmailRequest($request))->getMessageId();
    }

    private function sendRawFallback(WpPackPhpMailer $phpMailer): string
    {
        $mime = $phpMailer->getSentMIMEMessage();

        $request = [
            'Content' => ['Raw' => ['Data' => $mime]],
        ];

        if ($this->configurationSet !== null) {
            $request['ConfigurationSetName'] = $this->configurationSet;
        }

        return $this->sesClient->sendEmail(new SendEmailRequest($request))->getMessageId();
    }

    /**
     * @return array{ToAddresses: list<string>, CcAddresses?: list<string>, BccAddresses?: list<string>}
     */
    private function buildDestination(WpPackPhpMailer $phpMailer): array
    {
        $dest = [
            'ToAddresses' => array_map(
                fn(array $a): string => $this->formatAddress($a),
                $phpMailer->getToAddresses(),
            ),
        ];

        $cc = $phpMailer->getCcAddresses();
        if (!empty($cc)) {
            $dest['CcAddresses'] = array_map(
                fn(array $a): string => $this->formatAddress($a),
                $cc,
            );
        }

        $bcc = $phpMailer->getBccAddresses();
        if (!empty($bcc)) {
            $dest['BccAddresses'] = array_map(
                fn(array $a): string => $this->formatAddress($a),
                $bcc,
            );
        }

        return $dest;
    }

    /**
     * @return array{Html?: array{Data: string, Charset: string}, Text?: array{Data: string, Charset: string}}
     */
    private function buildSimpleBody(WpPackPhpMailer $phpMailer): array
    {
        $body = [];

        if ($phpMailer->ContentType === \PHPMailer\PHPMailer\PHPMailer::CONTENT_TYPE_TEXT_HTML) {
            $body['Html'] = ['Data' => $phpMailer->Body, 'Charset' => $phpMailer->CharSet];
            if (!empty($phpMailer->AltBody)) {
                $body['Text'] = ['Data' => $phpMailer->AltBody, 'Charset' => $phpMailer->CharSet];
            }
        } else {
            $body['Text'] = ['Data' => $phpMailer->Body, 'Charset' => $phpMailer->CharSet];
        }

        return $body;
    }

    public function __toString(): string
    {
        return 'ses+api://';
    }
}
