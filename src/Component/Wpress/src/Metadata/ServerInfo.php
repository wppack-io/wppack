<?php

declare(strict_types=1);

namespace WpPack\Component\Wpress\Metadata;

final class ServerInfo implements \JsonSerializable
{
    public function __construct(
        public readonly ?string $htaccess = null,
        public readonly ?string $webConfig = null,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            htaccess: $data['.htaccess'] ?? null,
            webConfig: $data['web.config'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return array_filter([
            '.htaccess' => $this->htaccess,
            'web.config' => $this->webConfig,
        ], static fn($v) => $v !== null);
    }
}
