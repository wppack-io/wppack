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

final class MultisiteMetadata implements \JsonSerializable
{
    /**
     * @param list<array<string, mixed>> $networks
     * @param list<SiteInfo> $sites
     * @param list<string> $plugins
     * @param list<string> $admins
     */
    public function __construct(
        public readonly bool $network,
        public readonly array $networks = [],
        public readonly array $sites = [],
        public readonly array $plugins = [],
        public readonly array $admins = [],
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
        $sites = [];

        foreach ($data['Sites'] ?? [] as $site) {
            $sites[] = SiteInfo::fromArray($site);
        }

        return new self(
            network: (bool) ($data['Network'] ?? false),
            networks: $data['Networks'] ?? [],
            sites: $sites,
            plugins: $data['Plugins'] ?? [],
            admins: $data['Admins'] ?? [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'Network' => $this->network,
            'Networks' => $this->networks,
            'Sites' => $this->sites,
            'Plugins' => $this->plugins,
            'Admins' => $this->admins,
        ];
    }
}
