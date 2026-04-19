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

namespace WPPack\Component\Monitoring\Bridge\Cloudflare;

use WPPack\Component\Monitoring\ProviderSettings;

final readonly class CloudflareProviderSettings extends ProviderSettings
{
    public function __construct(
        #[\SensitiveParameter]
        public string $apiToken = '',
        public string $hostname = '',
    ) {}

    public static function sensitiveFields(): array
    {
        return ['apiToken'];
    }

    public function toArray(): array
    {
        return [
            'apiToken' => $this->apiToken,
            'hostname' => $this->hostname,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            apiToken: (string) ($data['apiToken'] ?? ''),
            hostname: (string) ($data['hostname'] ?? ''),
        );
    }
}
