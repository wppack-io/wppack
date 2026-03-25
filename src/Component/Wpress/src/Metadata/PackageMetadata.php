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

final class PackageMetadata implements \JsonSerializable
{
    /**
     * @param list<string>|null $plugins
     */
    public function __construct(
        public readonly string $siteUrl,
        public readonly string $homeUrl,
        public readonly ?string $internalSiteUrl = null,
        public readonly ?string $internalHomeUrl = null,
        public readonly ?ReplaceInfo $replace = null,
        public readonly ?bool $noSpamComments = null,
        public readonly ?bool $noPostRevisions = null,
        public readonly ?bool $noMedia = null,
        public readonly ?bool $noThemes = null,
        public readonly ?bool $noInactiveThemes = null,
        public readonly ?bool $noMustUsePlugins = null,
        public readonly ?bool $noPlugins = null,
        public readonly ?bool $noInactivePlugins = null,
        public readonly ?bool $noCache = null,
        public readonly ?bool $noDatabase = null,
        public readonly ?bool $noEmailReplace = null,
        public readonly ?PluginInfo $plugin = null,
        public readonly ?WordPressInfo $wordPress = null,
        public readonly ?DatabaseInfo $database = null,
        public readonly ?PhpInfo $php = null,
        public readonly ?array $plugins = null,
        public readonly ?string $template = null,
        public readonly ?string $stylesheet = null,
        public readonly ?string $uploads = null,
        public readonly ?string $uploadsUrl = null,
        public readonly ?ServerInfo $server = null,
        public readonly ?bool $encrypted = null,
        public readonly ?string $encryptedSignature = null,
        public readonly ?CompressionInfo $compression = null,
    ) {}

    public static function fromJson(string $json): self
    {
        $data = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);

        return self::fromArray($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            siteUrl: (string) ($data['SiteURL'] ?? ''),
            homeUrl: (string) ($data['HomeURL'] ?? ''),
            internalSiteUrl: $data['InternalSiteURL'] ?? null,
            internalHomeUrl: $data['InternalHomeURL'] ?? null,
            replace: isset($data['Replace']) ? ReplaceInfo::fromArray($data['Replace']) : null,
            noSpamComments: $data['NoSpamComments'] ?? null,
            noPostRevisions: $data['NoPostRevisions'] ?? null,
            noMedia: $data['NoMedia'] ?? null,
            noThemes: $data['NoThemes'] ?? null,
            noInactiveThemes: $data['NoInactiveThemes'] ?? null,
            noMustUsePlugins: $data['NoMustUsePlugins'] ?? null,
            noPlugins: $data['NoPlugins'] ?? null,
            noInactivePlugins: $data['NoInactivePlugins'] ?? null,
            noCache: $data['NoCache'] ?? null,
            noDatabase: $data['NoDatabase'] ?? null,
            noEmailReplace: $data['NoEmailReplace'] ?? null,
            plugin: isset($data['Plugin']) ? PluginInfo::fromArray($data['Plugin']) : null,
            wordPress: isset($data['WordPress']) ? WordPressInfo::fromArray($data['WordPress']) : null,
            database: isset($data['Database']) ? DatabaseInfo::fromArray($data['Database']) : null,
            php: isset($data['PHP']) ? PhpInfo::fromArray($data['PHP']) : null,
            plugins: $data['Plugins'] ?? null,
            template: $data['Template'] ?? null,
            stylesheet: $data['Stylesheet'] ?? null,
            uploads: $data['Uploads'] ?? null,
            uploadsUrl: $data['UploadsURL'] ?? null,
            server: isset($data['Server']) ? ServerInfo::fromArray($data['Server']) : null,
            encrypted: $data['Encrypted'] ?? null,
            encryptedSignature: $data['EncryptedSignature'] ?? null,
            compression: isset($data['Compression']) ? CompressionInfo::fromArray($data['Compression']) : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return array_filter([
            'SiteURL' => $this->siteUrl,
            'HomeURL' => $this->homeUrl,
            'InternalSiteURL' => $this->internalSiteUrl,
            'InternalHomeURL' => $this->internalHomeUrl,
            'Replace' => $this->replace,
            'NoSpamComments' => $this->noSpamComments,
            'NoPostRevisions' => $this->noPostRevisions,
            'NoMedia' => $this->noMedia,
            'NoThemes' => $this->noThemes,
            'NoInactiveThemes' => $this->noInactiveThemes,
            'NoMustUsePlugins' => $this->noMustUsePlugins,
            'NoPlugins' => $this->noPlugins,
            'NoInactivePlugins' => $this->noInactivePlugins,
            'NoCache' => $this->noCache,
            'NoDatabase' => $this->noDatabase,
            'NoEmailReplace' => $this->noEmailReplace,
            'Plugin' => $this->plugin,
            'WordPress' => $this->wordPress,
            'Database' => $this->database,
            'PHP' => $this->php,
            'Plugins' => $this->plugins,
            'Template' => $this->template,
            'Stylesheet' => $this->stylesheet,
            'Uploads' => $this->uploads,
            'UploadsURL' => $this->uploadsUrl,
            'Server' => $this->server,
            'Encrypted' => $this->encrypted,
            'EncryptedSignature' => $this->encryptedSignature,
            'Compression' => $this->compression,
        ], static fn($v) => $v !== null);
    }
}
