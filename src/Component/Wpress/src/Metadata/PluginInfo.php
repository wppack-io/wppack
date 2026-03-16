<?php

declare(strict_types=1);

namespace WpPack\Component\Wpress\Metadata;

final class PluginInfo implements \JsonSerializable
{
    public function __construct(
        public readonly string $version,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            version: (string) ($data['Version'] ?? ''),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'Version' => $this->version,
        ];
    }
}
