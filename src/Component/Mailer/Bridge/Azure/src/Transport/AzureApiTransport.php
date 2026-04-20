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
use WPPack\Component\Mailer\Exception\TransportException;
use WPPack\Component\Mailer\PhpMailer;
use WPPack\Component\Mailer\Transport\AbstractApiTransport;
use WPPack\Component\Serializer\Encoder\JsonEncoder;

final class AzureApiTransport extends AbstractApiTransport
{
    use AzureRequestTrait;

    private const HOST = '%s.communication.azure.com';
    private const DEFAULT_API_VERSION = '2024-07-01-preview';

    public function __construct(
        private readonly string $resourceName,
        #[\SensitiveParameter]
        private readonly string $accessKey,
        private readonly string $apiVersion = self::DEFAULT_API_VERSION,
        private readonly ?HttpClient $httpClient = null,
        private readonly JsonEncoder $jsonEncoder = new JsonEncoder(),
    ) {}

    public function getName(): string
    {
        return 'azure+api';
    }

    protected function doSendApi(PhpMailer $phpMailer): string
    {
        $payload = $this->buildPayload($phpMailer);
        $body = $this->jsonEncoder->encode($payload, 'json');

        $endpoint = sprintf(self::HOST, $this->resourceName);

        $result = $this->sendAzureRequest($endpoint, $this->apiVersion, $this->accessKey, $body, $this->httpClient);

        return isset($result['id']) && $result['id'] !== '' ? $result['id'] : throw new TransportException(
            'Azure email send succeeded but no message ID was returned.',
        );
    }

}
