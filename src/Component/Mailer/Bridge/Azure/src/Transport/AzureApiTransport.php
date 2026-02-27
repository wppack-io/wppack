<?php

declare(strict_types=1);

namespace WpPack\Component\Mailer\Bridge\Azure\Transport;

use WpPack\Component\HttpClient\HttpClient;
use WpPack\Component\Mailer\Exception\TransportException;
use WpPack\Component\Mailer\Transport\AbstractApiTransport;
use WpPack\Component\Mailer\PhpMailer;

final class AzureApiTransport extends AbstractApiTransport
{
    use AzureRequestTrait;

    private const DEFAULT_API_VERSION = '2024-07-01-preview';

    public function __construct(
        private readonly string $endpoint,
        private readonly string $accessKey,
        private readonly string $apiVersion = self::DEFAULT_API_VERSION,
        private readonly ?HttpClient $httpClient = null,
    ) {}

    public function getName(): string
    {
        return 'azureapi';
    }

    protected function doSendApi(PhpMailer $phpMailer): string
    {
        $payload = $this->buildPayload($phpMailer);
        $body = wp_json_encode($payload);

        if ($body === false) {
            throw new TransportException('Failed to encode email payload as JSON.');
        }

        $result = $this->sendAzureRequest($this->endpoint, $this->apiVersion, $this->accessKey, $body, $this->httpClient);

        return isset($result['id']) && $result['id'] !== '' ? $result['id'] : throw new TransportException(
            'Azure email send succeeded but no message ID was returned.',
        );
    }

}
