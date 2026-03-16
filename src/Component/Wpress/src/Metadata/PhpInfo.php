<?php

declare(strict_types=1);

namespace WpPack\Component\Wpress\Metadata;

final class PhpInfo implements \JsonSerializable
{
    public function __construct(
        public readonly ?string $version = null,
        public readonly ?string $system = null,
        public readonly ?int $integer = null,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            version: $data['Version'] ?? null,
            system: $data['System'] ?? null,
            integer: isset($data['Integer']) ? (int) $data['Integer'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return array_filter([
            'Version' => $this->version,
            'System' => $this->system,
            'Integer' => $this->integer,
        ], static fn($v) => $v !== null);
    }
}
