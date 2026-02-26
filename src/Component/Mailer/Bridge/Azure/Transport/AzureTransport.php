<?php

declare(strict_types=1);

namespace WpPack\Component\Mailer\Bridge\Azure\Transport;

use WpPack\Component\Mailer\Transport\AbstractTransport;
use WpPack\Component\Mailer\WpPackPhpMailer;

final class AzureTransport extends AbstractTransport
{
    use AzureRequestTrait;

    private const DEFAULT_API_VERSION = '2024-07-01-preview';

    public function __construct(
        private readonly string $endpoint,
        private readonly string $accessKey,
        private readonly string $apiVersion = self::DEFAULT_API_VERSION,
    ) {}

    protected function getMailerName(): string
    {
        return 'azure';
    }

    protected function doSend(WpPackPhpMailer $phpMailer): void
    {
        $payload = $this->buildPayload($phpMailer);
        $body = wp_json_encode($payload);

        if ($body === false) {
            throw new \RuntimeException('Failed to encode email payload as JSON.');
        }

        $result = $this->sendAzureRequest($this->endpoint, $this->apiVersion, $this->accessKey, $body);

        if (isset($result['id']) && $result['id'] !== '') {
            $phpMailer->setLastMessageId('<' . $result['id'] . '>');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(WpPackPhpMailer $phpMailer): array
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

    public function __toString(): string
    {
        return 'azure://';
    }
}
