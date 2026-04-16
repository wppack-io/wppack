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

namespace WpPack\Component\Monitoring\Bridge\CloudWatch;

use WpPack\Component\Monitoring\ProviderSettings;

final readonly class AwsProviderSettings extends ProviderSettings
{
    public function __construct(
        public string $region = '',
        #[\SensitiveParameter]
        public string $accessKeyId = '',
        #[\SensitiveParameter]
        public string $secretAccessKey = '',
    ) {}

    public static function sensitiveFields(): array
    {
        return ['accessKeyId', 'secretAccessKey'];
    }

    public function toArray(): array
    {
        return [
            'region' => $this->region,
            'accessKeyId' => $this->accessKeyId,
            'secretAccessKey' => $this->secretAccessKey,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            region: (string) ($data['region'] ?? ''),
            accessKeyId: (string) ($data['accessKeyId'] ?? ''),
            secretAccessKey: (string) ($data['secretAccessKey'] ?? ''),
        );
    }
}
