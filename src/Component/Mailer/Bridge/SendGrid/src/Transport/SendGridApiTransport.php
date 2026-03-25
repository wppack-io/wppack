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

namespace WpPack\Component\Mailer\Bridge\SendGrid\Transport;

use WpPack\Component\HttpClient\Exception\ConnectionException;
use WpPack\Component\HttpClient\HttpClient;
use WpPack\Component\Mailer\Exception\TransportException;
use WpPack\Component\Mailer\Transport\AbstractApiTransport;
use WpPack\Component\Mailer\PhpMailer;
use WpPack\Component\Serializer\Encoder\JsonEncoder;

final class SendGridApiTransport extends AbstractApiTransport
{
    private const API_ENDPOINT = 'https://api.sendgrid.com/v3/mail/send';

    public function __construct(
        private readonly string $apiKey,
        private readonly ?HttpClient $httpClient = null,
        private readonly JsonEncoder $jsonEncoder = new JsonEncoder(),
    ) {}

    public function getName(): string
    {
        return 'sendgrid+api';
    }

    protected function doSendApi(PhpMailer $phpMailer): string
    {
        $payload = [
            'personalizations' => [$this->buildPersonalization($phpMailer)],
            'from' => $this->formatEmailAddress($phpMailer->From, $phpMailer->FromName),
            'subject' => $phpMailer->Subject,
            'content' => $this->buildContent($phpMailer),
        ];

        $replyTo = $phpMailer->getReplyToAddresses();
        if (!empty($replyTo)) {
            $replyToList = array_map(
                fn(array $addr): array => $this->formatEmailAddress($addr[0], $addr[1]),
                array_values($replyTo),
            );

            if (count($replyToList) === 1) {
                $payload['reply_to'] = $replyToList[0];
            } else {
                $payload['reply_to_list'] = $replyToList;
            }
        }

        $attachments = $this->buildAttachments($phpMailer);
        if (!empty($attachments)) {
            $payload['attachments'] = $attachments;
        }

        $body = $this->jsonEncoder->encode($payload, 'json');

        try {
            $response = ($this->httpClient ?? new HttpClient())
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->timeout(30)
                ->post(self::API_ENDPOINT, ['body' => $body]);
        } catch (ConnectionException $e) {
            throw new TransportException(sprintf('SendGrid email send failed: %s', $e->getMessage()), 0, $e);
        }

        if ($response->failed()) {
            throw new TransportException(sprintf(
                'SendGrid email send failed with status %d: %s',
                $response->status(),
                $response->body(),
            ));
        }

        // SendGrid returns the message ID in the X-Message-Id header
        $messageId = $response->header('X-Message-Id');

        if ($messageId === null || $messageId === '') {
            throw new TransportException('SendGrid email send succeeded but no message ID was returned.');
        }

        return $messageId;
    }

    /**
     * @return array{to: list<array{email: string, name?: string}>, cc?: list<array{email: string, name?: string}>, bcc?: list<array{email: string, name?: string}>}
     */
    private function buildPersonalization(PhpMailer $phpMailer): array
    {
        $personalization = [
            'to' => array_map(
                fn(array $a): array => $this->formatEmailAddress($a[0], $a[1]),
                $phpMailer->getToAddresses(),
            ),
        ];

        $cc = $phpMailer->getCcAddresses();
        if (!empty($cc)) {
            $personalization['cc'] = array_map(
                fn(array $a): array => $this->formatEmailAddress($a[0], $a[1]),
                $cc,
            );
        }

        $bcc = $phpMailer->getBccAddresses();
        if (!empty($bcc)) {
            $personalization['bcc'] = array_map(
                fn(array $a): array => $this->formatEmailAddress($a[0], $a[1]),
                $bcc,
            );
        }

        return $personalization;
    }

    /**
     * @return list<array{type: string, value: string}>
     */
    private function buildContent(PhpMailer $phpMailer): array
    {
        $content = [];

        if ($phpMailer->ContentType === PhpMailer::CONTENT_TYPE_TEXT_HTML) {
            if (!empty($phpMailer->AltBody)) {
                $content[] = ['type' => 'text/plain', 'value' => $phpMailer->AltBody];
            }
            $content[] = ['type' => 'text/html', 'value' => $phpMailer->Body];
        } else {
            $content[] = ['type' => 'text/plain', 'value' => $phpMailer->Body];
        }

        return $content;
    }

    /**
     * @return list<array{content: string, filename: string, type: string, disposition: string, content_id?: string}>
     */
    private function buildAttachments(PhpMailer $phpMailer): array
    {
        $attachments = [];

        foreach ($phpMailer->getAttachments() as $attachment) {
            [$pathOrContent, , $name, , $type, $isString, $disposition, $cid] = $attachment;

            $data = $isString ? $pathOrContent : file_get_contents($pathOrContent);

            if ($data === false) {
                throw new TransportException(sprintf('Failed to read attachment file: %s', $pathOrContent));
            }

            $entry = [
                'content' => base64_encode($data),
                'filename' => $name,
                'type' => $type,
                'disposition' => $disposition,
            ];

            if ($disposition === 'inline' && $cid !== '') {
                $entry['content_id'] = $cid;
            }

            $attachments[] = $entry;
        }

        return $attachments;
    }

    /**
     * @return array{email: string, name?: string}
     */
    private function formatEmailAddress(string $email, string $name = ''): array
    {
        $address = ['email' => $email];
        if ($name !== '') {
            $address['name'] = $name;
        }

        return $address;
    }

}
