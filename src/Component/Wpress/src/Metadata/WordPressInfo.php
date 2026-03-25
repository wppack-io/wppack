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

final class WordPressInfo implements \JsonSerializable
{
    /**
     * @param list<string>|null $themes
     */
    public function __construct(
        public readonly ?string $version = null,
        public readonly ?string $absolute = null,
        public readonly ?string $content = null,
        public readonly ?string $plugins = null,
        public readonly ?array $themes = null,
        public readonly ?string $uploads = null,
        public readonly ?string $uploadsUrl = null,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            version: $data['Version'] ?? null,
            absolute: $data['Absolute'] ?? null,
            content: $data['Content'] ?? null,
            plugins: $data['Plugins'] ?? null,
            themes: $data['Themes'] ?? null,
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
            'Version' => $this->version,
            'Absolute' => $this->absolute,
            'Content' => $this->content,
            'Plugins' => $this->plugins,
            'Themes' => $this->themes,
            'Uploads' => $this->uploads,
            'UploadsURL' => $this->uploadsUrl,
        ], static fn($v) => $v !== null);
    }
}
