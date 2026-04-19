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

namespace WPPack\Component\Mailer\Bridge\Azure\Transport;

use WPPack\Component\HttpClient\HttpClient;
use WPPack\Component\HttpClient\Exception\ConnectionException;
use WPPack\Component\Mailer\Exception\TransportException;
use WPPack\Component\Mailer\PhpMailer;

/**
 * Shared functionality for Azure Communication Services Email transports.
 */
trait AzureRequestTrait
{
    /**
     * @return array<string, mixed>
     */
    private function buildPayload(PhpMailer $phpMailer): array
    {
        $payload = [
            'senderAddress' => $phpMailer->From,
            'content' => $this->buildContent($phpMailer),
            'recipients' => $this->buildRecipients($phpMailer),
        ];

        $replyTo = $phpMailer->getReplyToAddresses();
        if (!empty($replyTo)) {
            $payload['replyTo'] = array_map(
                fn(array $addr): array => $this->formatRecipient($addr),
                $replyTo,
            );
        }

        $attachments = $this->buildAttachments($phpMailer);
        if (!empty($attachments)) {
            $payload['attachments'] = $attachments;
        }

        return $payload;
    }

    /**
     * @return array{subject: string, plainText?: string, html?: string}
     */
    private function buildContent(PhpMailer $phpMailer): array
    {
        $content = ['subject' => $phpMailer->Subject];

        if ($phpMailer->ContentType === PhpMailer::CONTENT_TYPE_TEXT_HTML) {
            $content['html'] = $phpMailer->Body;
            if (!empty($phpMailer->AltBody)) {
                $content['plainText'] = $phpMailer->AltBody;
            }
        } else {
            $content['plainText'] = $phpMailer->Body;
        }

        return $content;
    }

    /**
     * @return array{to: list<array{address: string, displayName?: string}>, cc?: list<array{address: string, displayName?: string}>, bcc?: list<array{address: string, displayName?: string}>}
     */
    private function buildRecipients(PhpMailer $phpMailer): array
    {
        $recipients = [
            'to' => array_map(
                fn(array $a): array => $this->formatRecipient($a),
                $phpMailer->getToAddresses(),
            ),
        ];

        $cc = $phpMailer->getCcAddresses();
        if (!empty($cc)) {
            $recipients['cc'] = array_map(
                fn(array $a): array => $this->formatRecipient($a),
                $cc,
            );
        }

        $bcc = $phpMailer->getBccAddresses();
        if (!empty($bcc)) {
            $recipients['bcc'] = array_map(
                fn(array $a): array => $this->formatRecipient($a),
                $bcc,
            );
        }

        return $recipients;
    }

    /**
     * @param array{0: string, 1: string} $addr
     * @return array{address: string, displayName?: string}
     */
    private function formatRecipient(array $addr): array
    {
        $recipient = ['address' => $addr[0]];
        if (!empty($addr[1])) {
            $recipient['displayName'] = $addr[1];
        }

        return $recipient;
    }

    /**
     * @return list<array{name: string, contentType: string, contentInBase64: string, contentId?: string}>
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
                'name' => $name,
                'contentType' => $type,
                'contentInBase64' => base64_encode($data),
            ];

            if ($disposition === 'inline' && $cid !== '') {
                $entry['contentId'] = $cid;
            }

            $attachments[] = $entry;
        }

        return $attachments;
    }

    /**
     * Build HMAC-SHA256 authentication headers for Azure Communication Services.
     *
     * @return array<string, string>
     */
    private function buildAzureAuthHeaders(string $url, string $body, #[\SensitiveParameter] string $accessKey): array
    {
        $parsed = parse_url($url);
        $host = $parsed['host'] ?? '';
        $pathAndQuery = ($parsed['path'] ?? '/') . (isset($parsed['query']) ? '?' . $parsed['query'] : '');

        $date = gmdate('D, d M Y H:i:s \G\M\T');
        $contentHash = base64_encode(hash('sha256', $body, true));

        $stringToSign = sprintf("POST\n%s\n%s;%s;%s", $pathAndQuery, $date, $host, $contentHash);
        $decodedKey = base64_decode($accessKey, true);

        if ($decodedKey === false) {
            throw new TransportException('Failed to decode Azure access key.');
        }

        $signature = base64_encode(hash_hmac('sha256', $stringToSign, $decodedKey, true));

        return [
            'Content-Type' => 'application/json',
            'x-ms-date' => $date,
            'x-ms-content-sha256' => $contentHash,
            'Authorization' => sprintf(
                'HMAC-SHA256 SignedHeaders=x-ms-date;host;x-ms-content-sha256&Signature=%s',
                $signature,
            ),
        ];
    }

    /**
     * Send request to Azure Communication Services Email REST API.
     *
     * @return array<string, mixed>
     */
    private function sendAzureRequest(string $endpoint, string $apiVersion, #[\SensitiveParameter] string $accessKey, string $body, ?HttpClient $httpClient = null): array
    {
        $url = sprintf('https://%s/emails:send?api-version=%s', $endpoint, $apiVersion);
        $headers = $this->buildAzureAuthHeaders($url, $body, $accessKey);

        try {
            $response = ($httpClient ?? new HttpClient())
                ->withHeaders($headers)
                ->timeout(30)
                ->post($url, ['body' => $body]);
        } catch (ConnectionException $e) {
            throw new TransportException(sprintf('Azure email send failed: %s', $e->getMessage()), 0, $e);
        }

        if ($response->failed()) {
            throw new TransportException(sprintf(
                'Azure email send failed with status %d: %s',
                $response->status(),
                $response->body(),
            ));
        }

        $decoded = $response->json();

        if ($decoded === []) {
            throw new TransportException('Azure email send succeeded but returned invalid JSON response.');
        }

        return $decoded;
    }
}
