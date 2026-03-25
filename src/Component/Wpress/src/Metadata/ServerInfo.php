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

final class ServerInfo implements \JsonSerializable
{
    public function __construct(
        public readonly ?string $htaccess = null,
        public readonly ?string $webConfig = null,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            htaccess: $data['.htaccess'] ?? null,
            webConfig: $data['web.config'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return array_filter([
            '.htaccess' => $this->htaccess,
            'web.config' => $this->webConfig,
        ], static fn($v) => $v !== null);
    }
}
