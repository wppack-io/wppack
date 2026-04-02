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

namespace WpPack\Component\Monitoring;

final readonly class CloudflareProviderSettings extends ProviderSettings
{
    public function __construct(
        #[\SensitiveParameter]
        public string $apiToken = '',
    ) {}

    public static function sensitiveFields(): array
    {
        return ['apiToken'];
    }

    public function toArray(): array
    {
        return [
            'apiToken' => $this->apiToken,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            apiToken: (string) ($data['apiToken'] ?? ''),
        );
    }
}
