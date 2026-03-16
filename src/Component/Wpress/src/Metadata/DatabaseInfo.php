<?php

declare(strict_types=1);

namespace WpPack\Component\Wpress\Metadata;

final class DatabaseInfo implements \JsonSerializable
{
    /**
     * @param list<string>|null $excludedTables
     * @param list<string>|null $includedTables
     */
    public function __construct(
        public readonly ?string $version = null,
        public readonly ?string $charset = null,
        public readonly ?string $collate = null,
        public readonly ?string $prefix = null,
        public readonly ?array $excludedTables = null,
        public readonly ?array $includedTables = null,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            version: $data['Version'] ?? null,
            charset: $data['Charset'] ?? null,
            collate: $data['Collate'] ?? null,
            prefix: $data['Prefix'] ?? null,
            excludedTables: $data['ExcludedTables'] ?? null,
            includedTables: $data['IncludedTables'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return array_filter([
            'Version' => $this->version,
            'Charset' => $this->charset,
            'Collate' => $this->collate,
            'Prefix' => $this->prefix,
            'ExcludedTables' => $this->excludedTables,
            'IncludedTables' => $this->includedTables,
        ], static fn($v) => $v !== null);
    }
}
