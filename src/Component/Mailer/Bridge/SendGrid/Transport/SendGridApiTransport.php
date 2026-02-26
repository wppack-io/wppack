<?php

declare(strict_types=1);

namespace WpPack\Component\Mailer\Bridge\SendGrid\Transport;

use WpPack\Component\Mailer\Transport\AbstractApiTransport;
use WpPack\Component\Mailer\WpPackPhpMailer;

final class SendGridApiTransport extends AbstractApiTransport
{
    private const API_ENDPOINT = 'https://api.sendgrid.com/v3/mail/send';

    public function __construct(
        private readonly string $apiKey,
    ) {}

    protected function getMailerName(): string
    {
        return 'sendgridapi';
    }

    protected function doSendApi(WpPackPhpMailer $phpMailer): string
    {
        $payload = [
            'personalizations' => [$this->buildPersonalization($phpMailer)],
            'from' => $this->formatEmailAddress($phpMailer->From, $phpMailer->FromName),
            'subject' => $phpMailer->Subject,
            'content' => $this->buildContent($phpMailer),
        ];

        $replyTo = $phpMailer->getReplyToAddresses();
        if (!empty($replyTo)) {
            $first = array_values($replyTo)[0];
            $payload['reply_to'] = $this->formatEmailAddress($first[0], $first[1]);
        }

        $attachments = $this->buildAttachments($phpMailer);
        if (!empty($attachments)) {
            $payload['attachments'] = $attachments;
        }

        $body = wp_json_encode($payload);

        if ($body === false) {
            throw new \RuntimeException('Failed to encode email payload as JSON.');
        }

        /** @var array{body: string, response: array{code: int}, headers: \WpOrg\Requests\Utility\CaseInsensitiveDictionary}|\WP_Error $response */
        $response = wp_remote_post(self::API_ENDPOINT, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'body' => $body,
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            throw new \RuntimeException(sprintf('SendGrid email send failed: %s', $response->get_error_message()));
        }

        $statusCode = (int) wp_remote_retrieve_response_code($response);
        if ($statusCode < 200 || $statusCode >= 300) {
            throw new \RuntimeException(sprintf(
                'SendGrid email send failed with status %d: %s',
                $statusCode,
                wp_remote_retrieve_body($response),
            ));
        }

        // SendGrid returns the message ID in the X-Message-Id header
        $messageId = wp_remote_retrieve_header($response, 'x-message-id');

        if (!\is_string($messageId) || $messageId === '') {
            throw new \RuntimeException('SendGrid email send succeeded but no message ID was returned.');
        }

        return $messageId;
    }

    /**
     * @return array{to: list<array{email: string, name?: string}>, cc?: list<array{email: string, name?: string}>, bcc?: list<array{email: string, name?: string}>}
     */
    private function buildPersonalization(WpPackPhpMailer $phpMailer): array
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
    private function buildContent(WpPackPhpMailer $phpMailer): array
    {
        $content = [];

        if ($phpMailer->ContentType === \PHPMailer\PHPMailer\PHPMailer::CONTENT_TYPE_TEXT_HTML) {
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
     * @return list<array{content: string, filename: string, type: string, disposition: string}>
     */
    private function buildAttachments(WpPackPhpMailer $phpMailer): array
    {
        $attachments = [];

        foreach ($phpMailer->getAttachments() as $attachment) {
            [$pathOrContent, , $name, , $type, $isString, $disposition] = $attachment;

            $data = $isString ? $pathOrContent : file_get_contents($pathOrContent);

            if ($data === false) {
                continue;
            }

            $attachments[] = [
                'content' => base64_encode($data),
                'filename' => $name,
                'type' => $type,
                'disposition' => $disposition,
            ];
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

    public function __toString(): string
    {
        return 'sendgrid+api://';
    }
}
