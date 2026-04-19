<?php

/*
 * This file is part of the WPPack package.
 *
 * (c) Tsuyoshi Tsurushima
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace WPPack\Component\Mailer\Transport;

/**
 * Describes a single configuration field for a transport.
 *
 * The `dsnPart` property maps the field value to the corresponding DSN component:
 * - `user` → DSN user (access key, username)
 * - `password` → DSN password (secret key, API key)
 * - `host` → DSN host
 * - `port` → DSN port
 * - `option:{key}` → DSN query parameter (e.g., `option:region` → `?region=`)
 */
final readonly class TransportField
{
    /**
     * @param list<array{label: string, value: string}>|null $options Select options (null = free text input)
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
    ) {}
}
