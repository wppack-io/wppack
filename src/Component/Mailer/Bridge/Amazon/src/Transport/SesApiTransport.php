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

namespace WpPack\Component\Mailer\Bridge\Amazon\Transport;

use AsyncAws\Ses\Input\SendEmailRequest;
use AsyncAws\Ses\SesClient;
use WpPack\Component\Mailer\Exception\TransportException;
use WpPack\Component\Mailer\PhpMailer;
use WpPack\Component\Mailer\Transport\AbstractApiTransport;

final class SesApiTransport extends AbstractApiTransport
{
    public function __construct(
        private readonly SesClient $sesClient,
        private readonly ?string $configurationSet = null,
    ) {}

    public function getName(): string
    {
        return 'ses+api';
    }

    protected function doSendApi(PhpMailer $phpMailer): string
    {
        $simple = [
            'Subject' => ['Data' => $phpMailer->Subject, 'Charset' => $phpMailer->CharSet],
            'Body' => $this->buildSimpleBody($phpMailer),
        ];

        $attachments = $this->buildAttachments($phpMailer);
        if (!empty($attachments)) {
            $simple['Attachments'] = $attachments;
        }

        $request = [
            'FromEmailAddress' => $this->formatAddress([$phpMailer->From, $phpMailer->FromName]),
            'Destination' => $this->buildDestination($phpMailer),
            'Content' => [
                'Simple' => $simple,
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

        return $this->extractMessageId(
            $this->sesClient->sendEmail(new SendEmailRequest($request)),
        );
    }

    private function extractMessageId(\AsyncAws\Ses\Result\SendEmailResponse $result): string
    {
        $messageId = $result->getMessageId();

        if ($messageId === '' || $messageId === null) {
            throw new TransportException('SES email send succeeded but no message ID was returned.');
        }

        return $messageId;
    }

    /**
     * @return array{ToAddresses: list<string>, CcAddresses?: list<string>, BccAddresses?: list<string>}
     */
    private function buildDestination(PhpMailer $phpMailer): array
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
    private function buildSimpleBody(PhpMailer $phpMailer): array
    {
        $body = [];

        if ($phpMailer->ContentType === PhpMailer::CONTENT_TYPE_TEXT_HTML) {
            $body['Html'] = ['Data' => $phpMailer->Body, 'Charset' => $phpMailer->CharSet];
            if (!empty($phpMailer->AltBody)) {
                $body['Text'] = ['Data' => $phpMailer->AltBody, 'Charset' => $phpMailer->CharSet];
            }
        } else {
            $body['Text'] = ['Data' => $phpMailer->Body, 'Charset' => $phpMailer->CharSet];
        }

        return $body;
    }

    /**
     * @return list<array{RawContent: string, FileName: string, ContentType?: string, ContentDisposition?: string, ContentId?: string}>
     */
    private function buildAttachments(PhpMailer $phpMailer): array
    {
        $attachments = [];

        foreach ($phpMailer->getAttachments() as $att) {
            $content = $att[5] ? $att[0] : file_get_contents($att[0]);

            if ($content === false) {
                throw new TransportException(sprintf('Failed to read attachment file: %s', $att[0]));
            }

            $entry = [
                'RawContent' => $content,
                'FileName' => $att[2],
                'ContentType' => $att[4],
                'ContentDisposition' => strtoupper($att[6]),
            ];

            if (!empty($att[7])) {
                $entry['ContentId'] = $att[7];
            }

            $attachments[] = $entry;
        }

        return $attachments;
    }

}
