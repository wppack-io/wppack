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

final class SiteWordPressInfo implements \JsonSerializable
{
    public function __construct(
        public readonly ?string $uploads = null,
        public readonly ?string $uploadsUrl = null,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            uploads: $data['Uploads'] ?? null,
            uploadsUrl: $data['UploadsURL'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return array_filter([
            'Uploads' => $this->uploads,
            'UploadsURL' => $this->uploadsUrl,
        ], static fn($v) => $v !== null);
    }
}
