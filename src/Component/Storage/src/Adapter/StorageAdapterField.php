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

namespace WpPack\Component\Storage\Adapter;

/**
 * Describes a single configuration field for a storage adapter.
 *
 * The `dsnPart` property maps the field value to the corresponding DSN component:
 * - `user` → DSN user (access key)
 * - `password` → DSN password (secret key)
 * - `host` → DSN host
 * - `port` → DSN port
 * - `path` → DSN path
 * - `option:{key}` → DSN query parameter (e.g., `option:endpoint` → `?endpoint=`)
 */
final readonly class StorageAdapterField
{
    /**
     * @param list<array{label: string, value: string}>|null $options
     */
    public function __construct(
        public string $name,
        public string $label,
        public string $type = 'text',
        public bool $required = false,
        public ?string $default = null,
        public ?string $help = null,
        public ?string $dsnPart = null,
        public ?array $options = null,
        public ?string $maxWidth = null,
        public ?string $conditional = null,
    ) {}
}
