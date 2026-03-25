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

final class SiteInfo implements \JsonSerializable
{
    /**
     * @param list<string>|null $plugins
     */
    public function __construct(
        public readonly ?int $blogId = null,
        public readonly ?int $siteId = null,
        public readonly ?int $langId = null,
        public readonly ?string $siteUrl = null,
        public readonly ?string $homeUrl = null,
        public readonly ?string $domain = null,
        public readonly ?string $path = null,
        public readonly ?array $plugins = null,
        public readonly ?string $template = null,
        public readonly ?string $stylesheet = null,
        public readonly ?string $uploads = null,
        public readonly ?string $uploadsUrl = null,
        public readonly ?SiteWordPressInfo $wordPress = null,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            blogId: isset($data['BlogID']) ? (int) $data['BlogID'] : null,
            siteId: isset($data['SiteID']) ? (int) $data['SiteID'] : null,
            langId: isset($data['LangID']) ? (int) $data['LangID'] : null,
            siteUrl: $data['SiteURL'] ?? null,
            homeUrl: $data['HomeURL'] ?? null,
            domain: $data['Domain'] ?? null,
            path: $data['Path'] ?? null,
            plugins: $data['Plugins'] ?? null,
            template: $data['Template'] ?? null,
            stylesheet: $data['Stylesheet'] ?? null,
            uploads: $data['Uploads'] ?? null,
            uploadsUrl: $data['UploadsURL'] ?? null,
            wordPress: isset($data['WordPress']) ? SiteWordPressInfo::fromArray($data['WordPress']) : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return array_filter([
            'BlogID' => $this->blogId,
            'SiteID' => $this->siteId,
            'LangID' => $this->langId,
            'SiteURL' => $this->siteUrl,
            'HomeURL' => $this->homeUrl,
            'Domain' => $this->domain,
            'Path' => $this->path,
            'Plugins' => $this->plugins,
            'Template' => $this->template,
            'Stylesheet' => $this->stylesheet,
            'Uploads' => $this->uploads,
            'UploadsURL' => $this->uploadsUrl,
            'WordPress' => $this->wordPress,
        ], static fn($v) => $v !== null);
    }
}
