<?php

declare(strict_types=1);

namespace WpPack\Component\Wpress\Metadata;

final class ReplaceInfo implements \JsonSerializable
{
    /**
     * @param list<string> $oldValues
     * @param list<string> $newValues
     */
    public function __construct(
        public readonly array $oldValues = [],
        public readonly array $newValues = [],
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            oldValues: $data['OldValues'] ?? [],
            newValues: $data['NewValues'] ?? [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'OldValues' => $this->oldValues,
            'NewValues' => $this->newValues,
        ];
    }
}
