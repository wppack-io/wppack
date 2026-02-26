<?php

declare(strict_types=1);

namespace WpPack\Component\Mailer\Bridge\Azure\Transport;

use WpPack\Component\Mailer\WpPackPhpMailer;

/**
 * Shared functionality for Azure Communication Services Email transports.
 */
trait AzureRequestTrait
{
    /**
     * @return array{subject: string, plainText?: string, html?: string}
     */
    private function buildContent(WpPackPhpMailer $phpMailer): array
    {
        $content = ['subject' => $phpMailer->Subject];

        if ($phpMailer->ContentType === \PHPMailer\PHPMailer\PHPMailer::CONTENT_TYPE_TEXT_HTML) {
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
    private function buildRecipients(WpPackPhpMailer $phpMailer): array
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
     * @return list<array{name: string, contentType: string, contentInBase64: string}>
     */
    private function buildAttachments(WpPackPhpMailer $phpMailer): array
    {
        $attachments = [];

        foreach ($phpMailer->getAttachments() as $attachment) {
            [$pathOrContent, , $name, , $type, $isString, $disposition] = $attachment;

            if ($disposition === 'inline') {
                continue;
            }

            $data = $isString ? $pathOrContent : file_get_contents($pathOrContent);

            if ($data === false) {
                continue;
            }

            $attachments[] = [
                'name' => $name,
                'contentType' => $type,
                'contentInBase64' => base64_encode($data),
            ];
        }

        return $attachments;
    }

    /**
     * Build HMAC-SHA256 authentication headers for Azure Communication Services.
     *
     * @return array<string, string>
     */
    private function buildAzureAuthHeaders(string $url, string $body, string $accessKey): array
    {
        $parsed = parse_url($url);
        $host = $parsed['host'] ?? '';
        $pathAndQuery = ($parsed['path'] ?? '/') . (isset($parsed['query']) ? '?' . $parsed['query'] : '');

        $date = gmdate('D, d M Y H:i:s \G\M\T');
        $contentHash = base64_encode(hash('sha256', $body, true));

        $stringToSign = sprintf("POST\n%s\n%s;%s;%s", $pathAndQuery, $date, $host, $contentHash);
        $decodedKey = base64_decode($accessKey, true);

        if ($decodedKey === false) {
            throw new \RuntimeException('Failed to decode Azure access key.');
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
    private function sendAzureRequest(string $endpoint, string $apiVersion, string $accessKey, string $body): array
    {
        $url = sprintf('https://%s/emails:send?api-version=%s', $endpoint, $apiVersion);
        $headers = $this->buildAzureAuthHeaders($url, $body, $accessKey);

        /** @var array{body: string, response: array{code: int}}|\WP_Error $response */
        $response = wp_remote_post($url, [
            'headers' => $headers,
            'body' => $body,
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            throw new \RuntimeException(sprintf('Azure email send failed: %s', $response->get_error_message()));
        }

        $statusCode = (int) wp_remote_retrieve_response_code($response);
        if ($statusCode < 200 || $statusCode >= 300) {
            throw new \RuntimeException(sprintf(
                'Azure email send failed with status %d: %s',
                $statusCode,
                wp_remote_retrieve_body($response),
            ));
        }

        /** @var array<string, mixed> */
        return json_decode(wp_remote_retrieve_body($response), true) ?: [];
    }
}
