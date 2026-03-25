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

namespace WpPack\Component\Wpress\Metadata;

final class CompressionInfo implements \JsonSerializable
{
    public function __construct(
        public readonly bool $enabled = false,
        public readonly string $type = 'gzip',
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            enabled: (bool) ($data['Enabled'] ?? false),
            type: (string) ($data['Type'] ?? 'gzip'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'Enabled' => $this->enabled,
            'Type' => $this->type,
        ];
    }
}
